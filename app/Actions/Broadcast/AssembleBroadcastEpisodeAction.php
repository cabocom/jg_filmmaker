<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use App\Services\Broadcast\BroadcastMediaValidator;
use App\Services\Broadcast\ShotSubtitleGenerator;
use App\Services\Media\MediaStorageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final readonly class AssembleBroadcastEpisodeAction
{
    public function __construct(
        protected MediaStorageService $mediaStorage,
        protected BroadcastMediaValidator $mediaValidator,
        protected ShotSubtitleGenerator $subtitleGenerator,
        protected PublishEpisodeToTvDriveAction $tvDrivePublisher
    ) {}

    /**
     * Ensambla el episodio en 1080p, exporta versión podcast MP3 y versión vertical 9:16 para YouTube Short.
     */
    public function execute(BroadcastJob $job): BroadcastJob
    {
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $sortedShots = $this->resolveSortedShots($artifacts, (string) $job->id);
        $channelId = (string) $job->channel_id;
        $jobId = (string) $job->id;
        $tempDir = storage_path("app/private/broadcast/{$jobId}/temp");
        File::ensureDirectoryExists($tempDir);

        $localFiles = [
            '1080p' => "{$tempDir}/episode_1080p.mp4",
            'podcast' => "{$tempDir}/episode_podcast.mp3",
            'short' => "{$tempDir}/episode_short_9x16.mp4",
        ];

        $this->concatEpisode1080p($sortedShots, $localFiles['1080p'], $tempDir, $jobId);
        $this->exportPodcastMp3($localFiles['1080p'], $localFiles['podcast'], $artifacts, $jobId);
        $this->exportShort9x16($this->selectHighlightShots($sortedShots), $localFiles['short'], $tempDir, $jobId);

        $probes = [
            'episode_1080p' => $this->mediaValidator->assertValidMp4($localFiles['1080p']),
            'episode_short_9x16' => $this->mediaValidator->assertValidMp4($localFiles['short']),
        ];
        $storagePaths = [
            'episode_1080p' => "broadcast/{$channelId}/{$jobId}/episode_1080p.mp4",
            'episode_podcast_mp3' => "broadcast/{$channelId}/{$jobId}/episode_podcast.mp3",
            'episode_short_9x16' => "broadcast/{$channelId}/{$jobId}/episode_short_9x16.mp4",
        ];

        $this->storeArtifacts($storagePaths, $localFiles);
        $tvDrivePath = $this->tvDrivePublisher->publishIfConfigured($job, $localFiles['1080p']);
        $this->cleanupTemporaryFiles($jobId, $tempDir);

        return $this->finalizeJob($job, $artifacts, $storagePaths, $probes, $tvDrivePath);
    }

    /**
     * Selecciona los shots destacados que encajen en la ventana objetivo de 35 a 55 segundos.
     *
     * @param  list<array<string, mixed>>  $shots
     * @return list<array<string, mixed>>
     */
    public function selectHighlightShots(array $shots): array
    {
        $selected = [];
        $totalDuration = 0.0;

        foreach ($shots as $shot) {
            $duration = (float) ($shot['duration'] ?? $shot['estimated_seconds'] ?? 15.0);
            if ($totalDuration + $duration > 55.0 && ! empty($selected)) {
                break;
            }

            $selected[] = $shot;
            $totalDuration += $duration;

            if ($totalDuration >= 35.0 && $totalDuration <= 55.0) {
                break;
            }
        }

        if (empty($selected) && ! empty($shots)) {
            $selected[] = $shots[0];
        }

        return $selected;
    }

    /**
     * Valida la presencia de planos renderizados y los ordena correlativamente.
     *
     * @param  array<string, mixed>  $artifacts
     * @return list<array<string, mixed>>
     */
    private function resolveSortedShots(array $artifacts, string $jobId): array
    {
        $renderedShots = $artifacts['rendered_shots'] ?? [];
        if (! is_array($renderedShots) || empty($renderedShots)) {
            throw new RuntimeException("No rendered shots available for broadcast job {$jobId}.");
        }

        $sorted = array_values($renderedShots);
        usort($sorted, static fn (array $a, array $b): int => ((int) ($a['shot_number'] ?? 0)) <=> ((int) ($b['shot_number'] ?? 0)));

        return $sorted;
    }

    /**
     * Concatena los clips Mezzanine en el master 1080p con stream copy.
     *
     * @param  list<array<string, mixed>>  $shots
     */
    private function concatEpisode1080p(array $shots, string $outputPath, string $tempDir, string $jobId): void
    {
        if ($this->isMockMode()) {
            File::put($outputPath, "MOCK_EPISODE_1080P_{$jobId}");

            return;
        }

        $listPath = "{$tempDir}/concat_list.txt";
        $lines = array_map(function (array $shot): string {
            $clipPath = (string) ($shot['clip_path'] ?? '');
            if ($clipPath === '' || ! File::exists($clipPath)) {
                throw new RuntimeException("Clip file not found for shot {$shot['shot_id']}: {$clipPath}");
            }

            return "file '".str_replace("'", "'\\''", $clipPath)."'";
        }, $shots);
        File::put($listPath, implode("\n", $lines));

        $result = Process::timeout(300)->run([
            'ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', $listPath,
            '-r', '30', '-fps_mode', 'cfr', '-video_track_timescale', '30000',
            '-c:v', 'libx264', '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-ar', '48000', '-ac', '2', '-movflags', '+faststart', $outputPath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("Fallo al concatenar episode 1080p para el job {$jobId}: ".$result->errorOutput());
        }
    }

    /**
     * Exporta el master de audio normalizado a estándar podcast EBU R128 (-16 LUFS).
     *
     * @param  array<string, mixed>  $artifacts
     */
    private function exportPodcastMp3(string $video1080pPath, string $outputPath, array $artifacts, string $jobId): void
    {
        if ($this->isMockMode()) {
            File::put($outputPath, "MOCK_EPISODE_PODCAST_MP3_{$jobId}");

            return;
        }

        $intro = $artifacts['channel_intro_path'] ?? null;
        $outro = $artifacts['channel_outro_path'] ?? null;
        $hasIntro = is_string($intro) && $intro !== '' && File::exists($intro);
        $hasOutro = is_string($outro) && $outro !== '' && File::exists($outro);

        $filter = match (true) {
            $hasIntro && $hasOutro => ['-i', $intro, '-i', $video1080pPath, '-i', $outro, '-filter_complex', '[0:a][1:a][2:a]concat=n=3:v=0:a=1,loudnorm=I=-16:TP=-1.5:LRA=11[aout]', '-map', '[aout]'],
            $hasIntro => ['-i', $intro, '-i', $video1080pPath, '-filter_complex', '[0:a][1:a]concat=n=2:v=0:a=1,loudnorm=I=-16:TP=-1.5:LRA=11[aout]', '-map', '[aout]'],
            $hasOutro => ['-i', $video1080pPath, '-i', $outro, '-filter_complex', '[0:a][1:a]concat=n=2:v=0:a=1,loudnorm=I=-16:TP=-1.5:LRA=11[aout]', '-map', '[aout]'],
            default => ['-i', $video1080pPath, '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11'],
        };

        $cmd = array_merge(['ffmpeg', '-y'], $filter, ['-c:a', 'libmp3lame', '-ar', '48000', '-b:a', '192k', $outputPath]);
        $result = Process::timeout(300)->run($cmd);
        if (! $result->successful()) {
            throw new RuntimeException("Fallo al exportar podcast MP3 para el job {$jobId}: ".$result->errorOutput());
        }
    }

    /**
     * Exporta el formato vertical 9:16 (YouTube Short) con subtítulos .ass quemados.
     *
     * @param  list<array<string, mixed>>  $highlightShots
     */
    private function exportShort9x16(array $highlightShots, string $outputPath, string $tempDir, string $jobId): void
    {
        if ($this->isMockMode()) {
            File::put($outputPath, "MOCK_EPISODE_SHORT_9X16_{$jobId}");

            return;
        }

        $duration = 0.0;
        $texts = [];
        foreach ($highlightShots as $shot) {
            $duration += (float) ($shot['duration'] ?? $shot['estimated_seconds'] ?? 15.0);
            if (! empty($shot['oral_text'])) {
                $texts[] = trim((string) $shot['oral_text']);
            }
        }

        $shortAssPath = "{$tempDir}/short_subtitles.ass";
        $this->subtitleGenerator->generateAss(implode(' ', $texts), $duration, $shortAssPath, verticalShort: true);

        $lines = array_map(fn (array $s): string => "file '".str_replace("'", "'\\''", (string) ($s['clip_path'] ?? ''))."'", $highlightShots);
        File::put("{$tempDir}/short_concat_list.txt", implode("\n", $lines));

        $vf = sprintf('scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,subtitles=%s', $shortAssPath);
        $result = Process::timeout(300)->run([
            'ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', "{$tempDir}/short_concat_list.txt",
            '-r', '30', '-c:v', 'libx264', '-profile:v', 'high', '-level', '4.1',
            '-pix_fmt', 'yuv420p', '-vf', $vf, '-c:a', 'aac', '-ar', '48000', '-ac', '2', $outputPath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("Fallo al exportar short 9:16 para el job {$jobId}: ".$result->errorOutput());
        }
    }

    /**
     * Almacena los 3 archivos finales en el almacenamiento multimedia agnóstico.
     *
     * @param  array<string, string>  $storagePaths
     * @param  array<string, string>  $localFiles
     */
    private function storeArtifacts(array $storagePaths, array $localFiles): void
    {
        $this->mediaStorage->put($storagePaths['episode_1080p'], (string) file_get_contents($localFiles['1080p']));
        $this->mediaStorage->put($storagePaths['episode_podcast_mp3'], (string) file_get_contents($localFiles['podcast']));
        $this->mediaStorage->put($storagePaths['episode_short_9x16'], (string) file_get_contents($localFiles['short']));
    }

    /**
     * Actualiza el job a COMPLETED con la metadata de artefactos exportados.
     *
     * @param  array<string, mixed>  $artifacts
     * @param  array<string, string>  $storagePaths
     * @param  array<string, mixed>  $probes
     */
    private function finalizeJob(
        BroadcastJob $job,
        array $artifacts,
        array $storagePaths,
        array $probes,
        ?string $tvDrivePath = null
    ): BroadcastJob {
        $artifacts['exports'] = [
            'episode_1080p' => $storagePaths['episode_1080p'],
            'episode_podcast_mp3' => $storagePaths['episode_podcast_mp3'],
            'episode_short_9x16' => $storagePaths['episode_short_9x16'],
            'tv_drive_relative_path' => $tvDrivePath,
            'ffprobe' => $probes,
            'temp_clips_purged' => true,
        ];
        $job->stage = BroadcastJobStage::COMPLETED;
        $job->artifacts = $artifacts;
        $job->save();

        return $job;
    }

    /**
     * Purga los clips temporales y archivos intermedios de ensamblado.
     */
    private function cleanupTemporaryFiles(string $jobId, string $tempDir): void
    {
        $clipsDir = storage_path("app/private/broadcast/{$jobId}/clips");
        if (File::isDirectory($clipsDir)) {
            File::deleteDirectory($clipsDir);
        }

        if (File::isDirectory($tempDir)) {
            File::deleteDirectory($tempDir);
        }
    }

    private function isMockMode(): bool
    {
        return app()->environment('testing') || (bool) config('services.filmmaker.mock_ffmpeg', false);
    }
}
