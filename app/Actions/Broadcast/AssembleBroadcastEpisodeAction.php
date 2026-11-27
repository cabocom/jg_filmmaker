<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use App\Services\Broadcast\BroadcastMediaValidator;
use App\Services\Broadcast\IntroCardGenerator;
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
        protected PublishEpisodeToTvDriveAction $tvDrivePublisher,
        protected ?IntroCardGenerator $introCardGenerator = null,
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

        $publishPodcast = ($artifacts['publish_to_podcast'] ?? true) !== false;
        $generateShort = ($artifacts['generate_short'] ?? true) !== false;
        $localFiles = ['1080p' => "{$tempDir}/episode_1080p.mp4", 'podcast' => "{$tempDir}/episode_podcast.mp3", 'short' => "{$tempDir}/episode_short_9x16.mp4"];
        if ($this->isMockMode()) {
            $this->concatEpisode1080p($sortedShots, $localFiles['1080p'], $tempDir, $jobId, $artifacts);
        } else {
            $bodyPath = "{$tempDir}/body_1080p.mp4";
            $this->concatEpisode1080p($sortedShots, $bodyPath, $tempDir, $jobId, $artifacts);
            $introGenerator = $this->introCardGenerator ?? app(IntroCardGenerator::class);
            $introPath = $introGenerator->generate($job, $artifacts, $tempDir);
            $outroPath = $introGenerator->generateOutro($job, $artifacts, $tempDir);
            $this->concatVideoSegments([$introPath, $bodyPath, $outroPath], $localFiles['1080p'], $jobId, $artifacts);
        }
        if ($publishPodcast) {
            $this->exportPodcastMp3($localFiles['1080p'], $localFiles['podcast'], $artifacts, $jobId);
        }
        if ($generateShort) {
            $this->exportShort9x16($this->selectHighlightShots($sortedShots), $localFiles['short'], $tempDir, $jobId, $artifacts);
        }

        $probes = ['episode_1080p' => $this->mediaValidator->assertValidMp4($localFiles['1080p'])];
        if ($generateShort) {
            $probes['episode_short_9x16'] = $this->mediaValidator->assertValidMp4($localFiles['short']);
        }
        $storagePaths = [
            'episode_1080p' => "broadcast/{$channelId}/{$jobId}/episode_1080p.mp4",
            'episode_podcast_mp3' => $publishPodcast ? "broadcast/{$channelId}/{$jobId}/episode_podcast.mp3" : null,
            'episode_short_9x16' => $generateShort ? "broadcast/{$channelId}/{$jobId}/episode_short_9x16.mp4" : null,
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
     * Concatena los clips Mezzanine en el master 1080p (con mux de audio maestro o stream copy).
     *
     * @param  list<array<string, mixed>>  $shots
     * @param  array<string, mixed>  $artifacts
     */
    private function concatEpisode1080p(array $shots, string $outputPath, string $tempDir, string $jobId, array $artifacts = []): void
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

        $masterAudioPath = $artifacts['master_audio']['audio_path'] ?? null;
        $cmd = is_string($masterAudioPath) && $masterAudioPath !== '' && File::exists($masterAudioPath)
            ? $this->buildMasterAudioConcatCommand($listPath, $masterAudioPath, $outputPath, $artifacts)
            : ['ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', $listPath, '-c', 'copy', '-movflags', '+faststart', $outputPath];
        $result = Process::timeout(300)->run($cmd);
        if (! $result->successful()) {
            throw new RuntimeException("Fallo al concatenar episode 1080p para el job {$jobId}: ".$result->errorOutput());
        }
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $artifacts
     */
    private function concatVideoSegments(array $segments, string $outputPath, string $jobId, array $artifacts = []): void
    {
        $inputs = [];
        $filterParts = [];
        foreach (array_values($segments) as $index => $path) {
            $inputs[] = '-i';
            $inputs[] = $path;
            $filterParts[] = "[{$index}:v:0][{$index}:a:0]";
        }

        $n = count($segments);
        $filterComplex = implode('', $filterParts)."concat=n={$n}:v=1:a=1[v][a]";
        $is720p = (($artifacts['resolution_profile'] ?? '1080p30') === '720p30');
        $preset = $is720p ? ['-preset', 'veryfast'] : [];

        $cmd = [
            'ffmpeg', '-y', ...$inputs,
            '-filter_complex', $filterComplex,
            '-map', '[v]', '-map', '[a]',
            '-r', '30', '-fps_mode', 'cfr', '-video_track_timescale', '30000',
            '-c:v', 'libx264', '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p', ...$preset,
            '-c:a', 'aac', '-ar', '48000', '-ac', '2',
            '-movflags', '+faststart', $outputPath,
        ];

        $result = Process::timeout(300)->run($cmd);
        if (! $result->successful()) {
            throw new RuntimeException("Fallo al unir intro/cuerpo/outro para el job {$jobId}: ".$result->errorOutput());
        }
    }

    /**
     * @param  array<string, mixed>  $artifacts
     * @return list<string>
     */
    public function buildMasterAudioConcatCommand(string $listPath, string $masterAudioPath, string $outputPath, array $artifacts): array
    {
        $cmd = ['ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', $listPath, '-i', $masterAudioPath];
        $globalAss = $artifacts['master_audio']['ass_path'] ?? $artifacts['master_audio']['global_ass_path'] ?? $artifacts['global_ass_path'] ?? null;
        if (is_string($globalAss) && $globalAss !== '' && File::exists($globalAss)) {
            $cmd = [...$cmd, '-vf', 'subtitles='.$this->escapeFilterPath($globalAss)];
        }
        $is720p = (($artifacts['resolution_profile'] ?? '1080p30') === '720p30');
        $preset = $is720p ? ['-preset', 'veryfast'] : [];

        return [
            ...$cmd, '-r', '30', '-fps_mode', 'cfr', '-video_track_timescale', '30000',
            '-c:v', 'libx264', '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p', ...$preset,
            '-map', '0:v:0', '-map', '1:a:0', '-c:a', 'aac', '-ar', '48000', '-ac', '2',
            '-shortest', '-movflags', '+faststart', $outputPath,
        ];
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
     * @param  array<string, mixed>  $artifacts
     */
    private function exportShort9x16(array $highlightShots, string $outputPath, string $tempDir, string $jobId, array $artifacts = []): void
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
        $listPath = "{$tempDir}/short_concat_list.txt";
        $lines = array_map(fn (array $s): string => "file '".str_replace("'", "'\\''", (string) ($s['clip_path'] ?? ''))."'", $highlightShots);
        File::put($listPath, implode("\n", $lines));
        $result = Process::timeout(300)->run($this->buildShort9x16Command($listPath, $shortAssPath, $outputPath, $artifacts));
        if (! $result->successful()) {
            throw new RuntimeException("Fallo al exportar short 9:16 para el job {$jobId}: ".$result->errorOutput());
        }
    }

    /** @param array<string, mixed> $artifacts @return list<string> */
    public function buildShort9x16Command(string $listPath, string $shortAssPath, string $outputPath, array $artifacts): array
    {
        $masterAudioPath = $artifacts['master_audio']['audio_path'] ?? null;
        $hasMasterAudio = is_string($masterAudioPath) && $masterAudioPath !== '' && File::exists($masterAudioPath);
        $vf = 'scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,subtitles='.$this->escapeFilterPath($shortAssPath);
        $audioArgs = $hasMasterAudio
            ? ['-i', $masterAudioPath, '-map', '0:v:0', '-map', '1:a:0', '-shortest']
            : [];

        return [
            'ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', $listPath, ...$audioArgs,
            '-r', '30', '-c:v', 'libx264', '-profile:v', 'high', '-level', '4.1',
            '-pix_fmt', 'yuv420p', '-vf', $vf, '-c:a', 'aac', '-ar', '48000', '-ac', '2', $outputPath,
        ];
    }

    /**
     * Almacena los archivos finales generados en el almacenamiento multimedia agnóstico.
     *
     * @param  array<string, ?string>  $storagePaths
     * @param  array<string, string>  $localFiles
     */
    private function storeArtifacts(array $storagePaths, array $localFiles): void
    {
        foreach (['episode_1080p' => '1080p', 'episode_podcast_mp3' => 'podcast', 'episode_short_9x16' => 'short'] as $key => $local) {
            if (! empty($storagePaths[$key])) {
                $this->mediaStorage->put($storagePaths[$key], (string) file_get_contents($localFiles[$local]));
            }
        }
    }

    /**
     * Actualiza el job a COMPLETED con la metadata de artefactos exportados.
     *
     * @param  array<string, mixed>  $artifacts
     * @param  array<string, ?string>  $storagePaths
     * @param  array<string, mixed>  $probes
     */
    private function finalizeJob(BroadcastJob $job, array $artifacts, array $storagePaths, array $probes, ?string $tvDrivePath = null): BroadcastJob
    {
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
        foreach ([storage_path("app/private/broadcast/{$jobId}/clips"), $tempDir] as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
    }

    private function escapeFilterPath(string $path): string
    {
        return str_replace(['\\', ':', "'", ' '], ['\\\\', '\\:', "\\'", '\\ '], trim($path));
    }

    private function isMockMode(): bool
    {
        return app()->environment('testing') || (bool) config('services.filmmaker.mock_ffmpeg', false);
    }
}
