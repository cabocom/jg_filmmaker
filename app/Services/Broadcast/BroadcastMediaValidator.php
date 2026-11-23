<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class BroadcastMediaValidator
{
    public const float EXPECTED_FPS = 30.0;

    public const float FPS_TOLERANCE = 0.5;

    /**
     * Valida un archivo de vídeo MP4 según los estándares de emisión (H.264, CFR 30fps, audio AAC).
     *
     * @return array{
     *     valid: bool,
     *     fps: float,
     *     is_cfr: bool,
     *     video_codec: string,
     *     audio_codec: string,
     *     duration: float,
     *     has_non_silent_audio: bool,
     *     errors: list<string>
     * }
     */
    public function validateMp4(string $filePath, bool $forceProbe = false): array
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            $error = ! file_exists($filePath)
                ? "File does not exist: {$filePath}"
                : "File is empty: {$filePath}";

            return $this->buildInvalidReport([$error]);
        }

        if (! $forceProbe && ($this->isTestingEnvironment() || (bool) config('services.filmmaker.mock_ffmpeg', false))) {
            return $this->buildMockReport();
        }

        $probeData = $this->probeFile($filePath);
        if ($probeData === null) {
            return $this->buildInvalidReport(["ffprobe failed or returned invalid JSON for file: {$filePath}"]);
        }

        return $this->analyzeProbeData($probeData);
    }

    /**
     * Valida el archivo MP4 y lanza RuntimeException si no cumple los requisitos.
     *
     * @return array{
     *     valid: bool,
     *     fps: float,
     *     is_cfr: bool,
     *     video_codec: string,
     *     audio_codec: string,
     *     duration: float,
     *     has_non_silent_audio: bool,
     *     errors: list<string>
     * }
     *
     * @throws RuntimeException
     */
    public function assertValidMp4(string $filePath, bool $forceProbe = false): array
    {
        $report = $this->validateMp4($filePath, $forceProbe);
        if (! $report['valid']) {
            throw new RuntimeException('MP4 media validation failed: '.implode('; ', $report['errors']));
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, fps: float, is_cfr: bool, video_codec: string, audio_codec: string, duration: float, has_non_silent_audio: bool, errors: list<string>}
     */
    private function analyzeProbeData(array $data): array
    {
        $streams = is_array($data['streams'] ?? null) ? $data['streams'] : [];
        $format = is_array($data['format'] ?? null) ? $data['format'] : [];

        $videoStream = $this->findStreamByType($streams, 'video');
        $audioStream = $this->findStreamByType($streams, 'audio');

        $errors = [];
        $videoAnalysis = $this->analyzeVideoStream($videoStream, $errors);
        $audioAnalysis = $this->analyzeAudioStream($audioStream, $format, $errors);

        $duration = (float) ($format['duration'] ?? $videoStream['duration'] ?? 0.0);

        return [
            'valid' => empty($errors),
            'fps' => $videoAnalysis['fps'],
            'is_cfr' => $videoAnalysis['is_cfr'],
            'video_codec' => $videoAnalysis['codec'],
            'audio_codec' => $audioAnalysis['codec'],
            'duration' => $duration,
            'has_non_silent_audio' => $audioAnalysis['has_non_silent'],
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stream
     * @param  list<string>  $errors
     * @return array{fps: float, is_cfr: bool, codec: string}
     */
    private function analyzeVideoStream(?array $stream, array &$errors): array
    {
        if ($stream === null) {
            $errors[] = 'No video stream found in MP4 file.';

            return ['fps' => 0.0, 'is_cfr' => false, 'codec' => ''];
        }

        $codec = (string) ($stream['codec_name'] ?? '');
        if (strtolower($codec) !== 'h264') {
            $errors[] = "Expected video codec h264, found: '{$codec}'.";
        }

        $avgFps = $this->parseFrameRate($stream['avg_frame_rate'] ?? null);
        $rFps = $this->parseFrameRate($stream['r_frame_rate'] ?? null);

        $avgValid = abs($avgFps - self::EXPECTED_FPS) <= self::FPS_TOLERANCE;
        $rValid = abs($rFps - self::EXPECTED_FPS) <= self::FPS_TOLERANCE;
        $isCfr = $avgValid && $rValid && abs($avgFps - $rFps) <= self::FPS_TOLERANCE;

        if (! $isCfr) {
            $errors[] = "Video is not CFR 30fps (avg_frame_rate: {$avgFps}, r_frame_rate: {$rFps}).";
        }

        return ['fps' => $avgFps > 0.0 ? $avgFps : $rFps, 'is_cfr' => $isCfr, 'codec' => $codec];
    }

    /**
     * @param  array<string, mixed>|null  $stream
     * @param  array<string, mixed>  $format
     * @param  list<string>  $errors
     * @return array{codec: string, has_non_silent: bool}
     */
    private function analyzeAudioStream(?array $stream, array $format, array &$errors): array
    {
        if ($stream === null) {
            $errors[] = 'No audio stream found in MP4 file.';

            return ['codec' => '', 'has_non_silent' => false];
        }

        $codec = (string) ($stream['codec_name'] ?? '');
        if (strtolower($codec) !== 'aac') {
            $errors[] = "Expected audio codec aac, found: '{$codec}'.";
        }

        $bitRate = (int) ($stream['bit_rate'] ?? $format['bit_rate'] ?? 0);
        $sampleRate = (int) ($stream['sample_rate'] ?? 0);
        $duration = (float) ($stream['duration'] ?? $format['duration'] ?? 0.0);

        $hasAudio = ($bitRate > 0) || ($sampleRate >= 44100 && $duration > 0.0);
        if (! $hasAudio) {
            $errors[] = 'Audio stream is silent or has zero bitrate/duration.';
        }

        return ['codec' => $codec, 'has_non_silent' => $hasAudio];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function probeFile(string $filePath): ?array
    {
        $process = Process::timeout(15)->run([
            'ffprobe',
            '-v',
            'error',
            '-print_format',
            'json',
            '-show_streams',
            '-show_format',
            $filePath,
        ]);

        if (! $process->successful()) {
            return null;
        }

        $decoded = json_decode($process->output(), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  list<array<string, mixed>>  $streams
     * @return array<string, mixed>|null
     */
    private function findStreamByType(array $streams, string $type): ?array
    {
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === $type) {
                return $stream;
            }
        }

        return null;
    }

    private function parseFrameRate(mixed $rate): float
    {
        if (! is_string($rate) || trim($rate) === '') {
            return 0.0;
        }

        if (str_contains($rate, '/')) {
            $parts = explode('/', $rate, 2);
            $num = (float) $parts[0];
            $den = (float) ($parts[1] ?? 1.0);

            return $den > 0.0 ? round($num / $den, 4) : 0.0;
        }

        return (float) $rate;
    }

    private function isTestingEnvironment(): bool
    {
        return function_exists('app') && app()->environment('testing');
    }

    /**
     * @param  list<string>  $errors
     * @return array{valid: bool, fps: float, is_cfr: bool, video_codec: string, audio_codec: string, duration: float, has_non_silent_audio: bool, errors: list<string>}
     */
    private function buildInvalidReport(array $errors): array
    {
        return [
            'valid' => false,
            'fps' => 0.0,
            'is_cfr' => false,
            'video_codec' => '',
            'audio_codec' => '',
            'duration' => 0.0,
            'has_non_silent_audio' => false,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{valid: bool, fps: float, is_cfr: bool, video_codec: string, audio_codec: string, duration: float, has_non_silent_audio: bool, errors: list<string>}
     */
    private function buildMockReport(): array
    {
        return [
            'valid' => true,
            'fps' => 30.0,
            'is_cfr' => true,
            'video_codec' => 'h264',
            'audio_codec' => 'aac',
            'duration' => 15.0,
            'has_non_silent_audio' => true,
            'errors' => [],
        ];
    }
}
