<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use App\Models\Shot;
use App\Services\Broadcast\ShotSubtitleGenerator;
use App\Services\Broadcast\Visuals\ShotVisualStrategyResolver;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final readonly class RenderShotClipAction
{
    public function __construct(
        protected ShotVisualStrategyResolver $visualResolver,
        protected TextToSpeechService $ttsService,
        protected ShotSubtitleGenerator $subtitleGenerator
    ) {}

    /**
     * Sintetiza el audio TTS, genera el visual, subtítulos sincronizados y ensambla el clip Mezzanine CFR.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(BroadcastJob $job, Shot $shot, array $context = []): array
    {
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $existing = $artifacts['rendered_shots'][$shot->id] ?? null;

        if (is_array($existing) && ! empty($existing['clip_path']) && file_exists((string) $existing['clip_path'])) {
            return $existing;
        }

        $oralText = trim((string) ($context['oral_text'] ?? ''));
        if ($oralText === '') {
            $rawText = trim((string) ($job->raw_text ?? ''));
            $title = trim((string) ($job->title ?? ''));
            $oralText = $rawText !== '' ? $rawText : $title;
        }
        $voice = isset($context['voice']) ? (string) $context['voice'] : null;
        $audioPath = $this->ttsService->synthesize($oralText, $voice, 1.0, $job->project);

        $strategy = $this->visualResolver->resolve((string) $job->visual_mode);
        $visualPath = $strategy->generateVisual($job, $shot, $context);

        $durationSeconds = (float) ($context['estimated_seconds'] ?? 15.0);
        [$srtPath, $assPath] = $this->generateSubtitles($job->id, $shot, $oralText, $durationSeconds);

        $clipPath = storage_path("app/private/broadcast/{$job->id}/clips/shot_{$shot->shot_number}_{$shot->id}.mp4");
        $this->ensureClipRendered($visualPath, $strategy->visualType(), $audioPath, $assPath, $clipPath, (string) $shot->id, $job);

        return $this->persistRenderedArtifacts($job, $shot, [
            'oral_text' => $oralText,
            'audio_path' => $audioPath,
            'visual_path' => $visualPath,
            'srt_path' => $srtPath,
            'ass_path' => $assPath,
            'clip_path' => $clipPath,
        ]);
    }

    /**
     * Genera subtítulos sincronizados en formatos .srt y .ass.
     *
     * @return array{0: string, 1: string}
     */
    private function generateSubtitles(string|int $jobId, Shot $shot, string $text, float $durationSeconds): array
    {
        $subtitlesDir = storage_path("app/private/broadcast/{$jobId}/subtitles");
        File::ensureDirectoryExists($subtitlesDir);

        $srtPath = "{$subtitlesDir}/shot_{$shot->shot_number}_{$shot->id}.srt";
        $assPath = "{$subtitlesDir}/shot_{$shot->shot_number}_{$shot->id}.ass";
        $this->subtitleGenerator->generateSrt($text, $durationSeconds, $srtPath);
        $this->subtitleGenerator->generateAss($text, $durationSeconds, $assPath);

        return [$srtPath, $assPath];
    }

    /**
     * Asegura la creación del archivo de clip, usando mock en tests o ffmpeg en producción.
     */
    private function ensureClipRendered(
        string $visualPath,
        string $visualType,
        string $audioPath,
        string $assPath,
        string $clipPath,
        string $shotId,
        ?BroadcastJob $job = null
    ): void {
        File::ensureDirectoryExists(dirname($clipPath));

        if (app()->environment('testing') || (bool) config('services.filmmaker.mock_ffmpeg', false)) {
            File::put($clipPath, 'MOCK_MP4_MEZZANINE_CFR_SHOT_'.$shotId);

            return;
        }

        $this->runFfmpeg($visualPath, $visualType, $audioPath, $assPath, $clipPath, $shotId, $job);
    }

    /**
     * Ensambla el clip en modo real utilizando ffmpeg con parámetros Mezzanine CFR.
     */
    private function runFfmpeg(
        string $visualPath,
        string $visualType,
        string $audioPath,
        string $assPath,
        string $clipPath,
        string $shotId,
        ?BroadcastJob $job = null
    ): void {
        $inputArgs = $visualType === 'video' ? ['-i', $visualPath] : ['-loop', '1', '-i', $visualPath];
        $is720p = (($job?->artifacts['resolution_profile'] ?? '1080p30') === '720p30');
        $w = $is720p ? 1280 : 1920;
        $h = $is720p ? 720 : 1080;
        $vfFilter = sprintf('scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2,subtitles=%s', $w, $h, $w, $h, $assPath);

        $ffmpegArgs = [
            'ffmpeg', '-y', ...$inputArgs, '-i', $audioPath,
            '-r', '30', '-c:v', 'libx264', '-profile:v', 'high', '-level', '4.1',
            '-pix_fmt', 'yuv420p', '-vf', $vfFilter, '-c:a', 'aac',
            '-ar', '48000', '-ac', '2', '-shortest', $clipPath,
        ];

        $result = Process::timeout(120)->run($ffmpegArgs);
        if (! $result->successful()) {
            throw new RuntimeException("Fallo en ffmpeg para el shot {$shotId}: ".$result->errorOutput());
        }
    }

    /**
     * Persiste el estado atómico del shot y avanza el stage a RENDERING si aplica.
     *
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    private function persistRenderedArtifacts(BroadcastJob $job, Shot $shot, array $data): array
    {
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $renderedShot = array_merge([
            'shot_id' => $shot->id,
            'shot_number' => $shot->shot_number,
            'rendered_at' => now()->toIso8601String(),
        ], $data);

        $artifacts['rendered_shots'][$shot->id] = $renderedShot;
        $job->artifacts = $artifacts;

        if ($job->stage === BroadcastJobStage::SHOTS) {
            $job->stage = BroadcastJobStage::RENDERING;
        }

        $job->save();

        return $renderedShot;
    }
}
