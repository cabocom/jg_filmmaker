<?php

declare(strict_types=1);

namespace App\Services\Tts;

use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Services\TextToSpeechService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class TtsManifestService
{
    public function __construct(
        protected TextToSpeechService $ttsService,
        protected ScreenplaySegmenter $segmenter,
        protected string $cacheDir
    ) {}

    public function buildManifestForScope(
        Project $project,
        ?string $sceneId = null,
        ?string $blockId = null,
        bool $persistBibleChanges = false
    ): array {
        $characters = $project->characters()->get();

        if ($blockId) {
            $block = ScriptBlock::where('id', $blockId)->whereHas('scene', fn ($q) => $q->where('project_id', $project->id))->firstOrFail();

            return $this->buildSegmentsManifest($project, $block->scene_id, $block->id, $this->segmenter->parseBlockToSegments($block, $characters, $project, $persistBibleChanges));
        }

        $scenesQuery = $project->scenes()->orderBy('scene_order');
        if ($sceneId) {
            $scenesQuery->where('id', $sceneId);
        }

        /** @var Collection<int, Scene> $scenes */
        $scenes = $scenesQuery->with(['scriptBlocks' => fn ($q) => $q->orderBy('block_order')])->get();
        $manifest = [];

        foreach ($scenes as $scene) {
            foreach ($scene->scriptBlocks as $block) {
                $segments = $this->segmenter->parseBlockToSegments($block, $characters, $project, $persistBibleChanges);
                $manifest = array_merge($manifest, $this->buildSegmentsManifest($project, $scene->id, $block->id, $segments));
            }
        }

        return $manifest;
    }

    protected function buildSegmentsManifest(Project $project, string $sceneId, string $blockId, array $segments): array
    {
        $manifest = [];
        foreach ($segments as $idx => $segment) {
            $manifest[] = [
                'block_id' => $blockId,
                'scene_id' => $sceneId,
                'type' => $segment['type'],
                'speaker' => $segment['speaker'],
                'voice' => $segment['voice'],
                'text' => $segment['text'],
                'stream_url' => route('projects.tts.stream', [
                    'project' => $project->id,
                    'block_id' => $blockId,
                    'segment_index' => $idx,
                ], false),
            ];
        }

        return $manifest;
    }

    public function isAudioCached(string $text, ?string $voice = null, float $speed = 1.0): bool
    {
        $path = $this->ttsService->getCachedAudioPath($text, $voice, $speed);

        return File::exists($path) && File::size($path) > 0;
    }

    public function isProjectAudioComplete(Project $project, float $speed = 1.0): bool
    {
        $cacheKey = "project:{$project->id}:audio_complete:".number_format($speed, 2, '.', '');

        return Cache::remember($cacheKey, 20, function () use ($project, $speed) {
            $manifest = $this->buildManifestForScope($project, persistBibleChanges: false);
            if (empty($manifest)) {
                return false;
            }

            foreach ($manifest as $segment) {
                $text = trim($segment['text'] ?? '');
                if ($text !== '' && ! $this->isAudioCached($text, $segment['voice'] ?? null, $speed)) {
                    return false;
                }
            }

            return true;
        });
    }

    public static function invalidateAudioCompleteCache(Project|string $project): void
    {
        $projectId = $project instanceof Project ? $project->id : $project;
        foreach ([1.0, 1.25, 1.5] as $s) {
            Cache::forget("project:{$projectId}:audio_complete:".number_format($s, 2, '.', ''));
        }
    }

    public function exportProjectToMp3(Project $project, float $speed = 1.0): string
    {
        $manifest = $this->buildManifestForScope($project);
        if (empty($manifest)) {
            throw new RuntimeException('El guión no contiene ningún segmento de texto locutable.');
        }

        $lines = $this->collectAudioFileLines($manifest, $speed);
        if (empty($lines)) {
            throw new RuntimeException('No hay archivos de audio válidos para concatenar.');
        }

        return $this->concatAudioWithFfmpeg($lines);
    }

    protected function collectAudioFileLines(array $manifest, float $speed): array
    {
        $lines = [];
        foreach ($manifest as $segment) {
            $text = trim($segment['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $path = $this->ttsService->getCachedAudioPath($text, $segment['voice'] ?? null, $speed);
            if (! File::exists($path) || File::size($path) === 0) {
                throw new RuntimeException('Faltan audios por generar antes de exportar el guión completo a MP3.');
            }
            $lines[] = "file '".addcslashes($path, "'")."'";
        }

        return $lines;
    }

    protected function concatAudioWithFfmpeg(array $lines): string
    {
        $tempDir = storage_path('framework/cache');
        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0775, true, true);
        }

        $listFile = $tempDir.'/concat_'.Str::uuid().'.txt';
        $outputMp3 = $tempDir.'/screenplay_'.Str::uuid().'.mp3';
        File::put($listFile, implode("\n", $lines));

        try {
            $cmd = ['ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', $listFile, '-c:a', 'libmp3lame', '-b:a', '192k', '-ar', '44100', $outputMp3];
            $result = Process::timeout(300)->run($cmd);

            if (! $result->successful() || ! File::exists($outputMp3) || File::size($outputMp3) === 0) {
                Log::error('Fallo en concatenación FFmpeg a MP3', ['error' => $result->errorOutput(), 'output' => $result->output()]);
                throw new RuntimeException('Error al procesar y convertir el audio a MP3 con FFmpeg.');
            }

            return $outputMp3;
        } finally {
            if (File::exists($listFile)) {
                File::delete($listFile);
            }
        }
    }

    public function prewarmManifest(array $manifest, float $speed = 1.0, ?callable $onProgress = null, ?Project $project = null): array
    {
        $stats = ['total' => count($manifest), 'cached' => 0, 'synthesized' => 0, 'failed' => 0];

        if (! $project && ! empty($manifest[0]['block_id'])) {
            $firstBlock = ScriptBlock::find($manifest[0]['block_id']);
            $project = $firstBlock?->scene?->project;
        }

        foreach ($manifest as $index => $item) {
            $text = trim($item['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $voice = $item['voice'] ?? null;
            if ($this->isAudioCached($text, $voice, $speed)) {
                $stats['cached']++;
                if ($onProgress) {
                    $onProgress($index, $item, 'cached');
                }

                continue;
            }

            try {
                $this->ttsService->synthesize($text, $voice, $speed, $project);
                $stats['synthesized']++;
                if ($onProgress) {
                    $onProgress($index, $item, 'synthesized');
                }
                usleep(150000);
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::warning("Fallo al pre-calentar segmento TTS #{$index}: ".$e->getMessage());
                if ($onProgress) {
                    $onProgress($index, $item, 'failed');
                }
            }
        }

        if ($project && $stats['synthesized'] > 0) {
            self::invalidateAudioCompleteCache($project);
        }

        return $stats;
    }
}
