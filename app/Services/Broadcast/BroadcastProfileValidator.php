<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

final class BroadcastProfileValidator
{
    public const array PROFILES = [
        '720p30' => ['width' => 1280, 'height' => 720, 'fps' => 30.0],
        '1080p30' => ['width' => 1920, 'height' => 1080, 'fps' => 30.0],
    ];

    public function assertValidProfile(string $profile): string
    {
        if (! array_key_exists($profile, self::PROFILES)) {
            throw new InvalidArgumentException("Perfil de broadcast no soportado: '{$profile}'. Perfiles válidos: 720p30, 1080p30.");
        }

        return $profile;
    }

    public function dimensionsFor(string $profile): array
    {
        $this->assertValidProfile($profile);

        return ['width' => self::PROFILES[$profile]['width'], 'height' => self::PROFILES[$profile]['height']];
    }

    public function inspectMp4(string $filePath, string $profile = '720p30', bool $forceProbe = false): array
    {
        $this->assertValidProfile($profile);
        if (! is_file($filePath) || filesize($filePath) === 0) {
            return $this->buildEmptyFileReport($filePath, $profile);
        }
        if (! $forceProbe && $this->shouldUseMock()) {
            return $this->buildMockReport($filePath, $profile);
        }

        $probeData = $this->probeFile($filePath);
        if ($probeData === null) {
            return $this->buildReport(
                false, $profile, 0, 0, 0.0, false, '', '', '', 0, 0, 0.0,
                ["ffprobe falló o devolvió JSON inválido para el archivo: {$filePath}"]
            );
        }

        return $this->analyzeProbeData($probeData, $profile);
    }

    private function shouldUseMock(): bool
    {
        return (function_exists('app') && app()->environment('testing')) || (bool) config('services.filmmaker.mock_ffmpeg', false);
    }

    private function buildEmptyFileReport(string $filePath, string $profile): array
    {
        $reason = ! is_file($filePath) ? "El archivo no existe: {$filePath}" : "El archivo está vacío: {$filePath}";

        return $this->buildReport(false, $profile, 0, 0, 0.0, false, '', '', '', 0, 0, 0.0, [$reason]);
    }

    private function buildMockReport(string $filePath, string $profile): array
    {
        $header = @file_get_contents($filePath, false, null, 0, 32) ?: '';
        if (str_starts_with($header, 'MOCK_INVALID_MP4')) {
            return $this->buildReport(
                false, $profile, 854, 480, 24.0, false, 'h264', 'yuv420p', 'aac', 44100, 2, 10.0,
                [
                    'Resolución de vídeo no conforme al perfil broadcast.',
                    'La tasa de cuadros no es CFR 30 fps.',
                    'La frecuencia de muestreo de audio debe ser 48000 Hz.',
                ]
            );
        }

        $target = self::PROFILES[$profile];

        return $this->buildReport(
            true, $profile, $target['width'], $target['height'], 30.0, true,
            'h264', 'yuv420p', 'aac', 48000, 2, 30.0, []
        );
    }

    private function probeFile(string $filePath): ?array
    {
        $process = Process::timeout(15)->run([
            'ffprobe', '-v', 'error', '-print_format', 'json', '-show_streams', '-show_format', $filePath,
        ]);
        if (! $process->successful()) {
            return null;
        }
        $decoded = json_decode($process->output(), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function analyzeProbeData(array $data, string $profile): array
    {
        $streams = is_array($data['streams'] ?? null) ? $data['streams'] : [];
        $format = is_array($data['format'] ?? null) ? $data['format'] : [];
        $reasons = [];

        $v = $this->analyzeVideoStream($this->findStream($streams, 'video'), $profile, $reasons);
        $a = $this->analyzeAudioStream($this->findStream($streams, 'audio'), $format, $reasons);

        $duration = (float) ($format['duration'] ?? $v['duration'] ?? $a['duration'] ?? 0.0);
        if ($duration <= 0.0) {
            $reasons[] = 'La duración total del archivo de medios es menor o igual a cero.';
        }

        return $this->buildReport(
            empty($reasons), $profile, $v['width'], $v['height'], $v['fps'], $v['is_cfr'],
            $v['codec'], $v['pix_fmt'], $a['codec'], $a['sample_rate'], $a['channels'],
            $duration, $reasons
        );
    }

    private function analyzeVideoStream(?array $s, string $profile, array &$reasons): array
    {
        if ($s === null) {
            $reasons[] = 'No se encontró stream de vídeo en el archivo MP4.';

            return ['width' => 0, 'height' => 0, 'fps' => 0.0, 'is_cfr' => false, 'codec' => '', 'pix_fmt' => '', 'duration' => 0.0];
        }
        $codec = strtolower((string) ($s['codec_name'] ?? ''));
        if ($codec !== 'h264') {
            $reasons[] = "Códec de vídeo '{$codec}' no válido. Se requiere h264.";
        }
        $pixFmt = (string) ($s['pix_fmt'] ?? '');
        if ($pixFmt !== 'yuv420p') {
            $reasons[] = "Formato de píxel '{$pixFmt}' no válido. Se requiere yuv420p.";
        }
        $w = (int) ($s['width'] ?? 0);
        $h = (int) ($s['height'] ?? 0);
        $target = self::PROFILES[$profile];
        if ($w !== $target['width'] || $h !== $target['height']) {
            $reasons[] = "Resolución {$w}x{$h} no coincide con perfil {$profile} ({$target['width']}x{$target['height']}).";
        }
        $fpsData = $this->evaluateFps($s['avg_frame_rate'] ?? null, $s['r_frame_rate'] ?? null, $reasons);

        return [
            'width' => $w, 'height' => $h, 'fps' => $fpsData['fps'], 'is_cfr' => $fpsData['is_cfr'],
            'codec' => $codec, 'pix_fmt' => $pixFmt, 'duration' => (float) ($s['duration'] ?? 0.0),
        ];
    }

    private function evaluateFps(mixed $avgRaw, mixed $rRaw, array &$reasons): array
    {
        $avgFps = $this->parseFrameRate($avgRaw);
        $rFps = $this->parseFrameRate($rRaw);
        $isCfr = abs($avgFps - 30.0) <= 0.5 && abs($rFps - 30.0) <= 0.5 && abs($avgFps - $rFps) <= 0.5;
        if (! $isCfr) {
            $reasons[] = "El vídeo no cumple CFR 30 fps (avg_frame_rate: {$avgFps}, r_frame_rate: {$rFps}).";
        }

        return ['fps' => $avgFps > 0.0 ? $avgFps : $rFps, 'is_cfr' => $isCfr];
    }

    private function analyzeAudioStream(?array $s, array $format, array &$reasons): array
    {
        if ($s === null) {
            $reasons[] = 'No se encontró stream de audio en el archivo MP4.';

            return ['codec' => '', 'sample_rate' => 0, 'channels' => 0, 'duration' => 0.0];
        }
        $codec = strtolower((string) ($s['codec_name'] ?? ''));
        if ($codec !== 'aac') {
            $reasons[] = "Códec de audio '{$codec}' no válido. Se requiere aac.";
        }
        $sampleRate = (int) ($s['sample_rate'] ?? 0);
        if ($sampleRate !== 48000) {
            $reasons[] = "Frecuencia de audio {$sampleRate} Hz no válida. Se requiere 48000 Hz.";
        }
        $channels = (int) ($s['channels'] ?? 0);
        if ($channels !== 2) {
            $reasons[] = "Canales de audio {$channels} no válidos. Se requiere estéreo (2 canales).";
        }
        $duration = (float) ($s['duration'] ?? $format['duration'] ?? 0.0);
        if ($duration <= 0.0) {
            $reasons[] = 'La duración del stream de audio es menor o igual a cero.';
        }

        return ['codec' => $codec, 'sample_rate' => $sampleRate, 'channels' => $channels, 'duration' => $duration];
    }

    private function findStream(array $streams, string $type): ?array
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

    private function buildReport(
        bool $compliant, string $profile, int $width, int $height, float $fps, bool $isCfr,
        string $vCodec, string $pixFmt, string $aCodec, int $sampleRate, int $channels,
        float $duration, array $reasons
    ): array {
        return [
            'broadcast_compliant' => $compliant, 'profile' => $profile, 'width' => $width,
            'height' => $height, 'fps' => $fps, 'is_cfr' => $isCfr, 'video_codec' => $vCodec,
            'pix_fmt' => $pixFmt, 'audio_codec' => $aCodec, 'sample_rate' => $sampleRate,
            'channels' => $channels, 'duration_seconds' => $duration, 'reasons' => $reasons,
        ];
    }
}
