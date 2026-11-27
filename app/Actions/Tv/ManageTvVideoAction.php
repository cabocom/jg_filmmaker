<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Actions\Broadcast\NormalizeBroadcastMediaAction;
use App\Models\TvVideo;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ManageTvVideoAction
{
    public function __construct(
        private readonly NormalizeBroadcastMediaAction $normalizeAction,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TvVideo
    {
        $type = (string) ($data['type'] ?? '');
        if (! in_array($type, ['bio', 'pill', 'news', 'debate', 'music'], true)) {
            throw new InvalidArgumentException("Tipo de vídeo inválido: [{$type}]. Permitidos: bio, pill, news, debate, music.");
        }

        return TvVideo::create([
            'channel_slug' => (string) ($data['channel_slug'] ?? 'ai-today'),
            'type' => $type,
            'title' => (string) ($data['title'] ?? ''),
            'source_ref' => isset($data['source_ref']) ? (string) $data['source_ref'] : null,
            'raw_text' => isset($data['raw_text']) ? (string) $data['raw_text'] : null,
            'publish_to_podcast' => (bool) ($data['publish_to_podcast'] ?? false),
            'generate_short' => (bool) ($data['generate_short'] ?? false),
            'evergreen_fallback' => (bool) ($data['evergreen_fallback'] ?? false),
            'stage' => 'pending',
            'status' => 'pending',
        ]);
    }

    public function assertNotBusy(TvVideo $video): void
    {
        if ($video->isBusy()) {
            throw new RuntimeException("El vídeo [{$video->id}] está en proceso ({$video->stage}) y no puede modificarse.", 409);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TvVideo $video, array $data): TvVideo
    {
        $this->assertNotBusy($video);

        if (array_key_exists('type', $data) && ! in_array($data['type'], ['bio', 'pill', 'news', 'debate', 'music'], true)) {
            throw new InvalidArgumentException("Tipo de vídeo inválido: [{$data['type']}]. Permitidos: bio, pill, news, debate, music.");
        }

        $allowed = ['type', 'title', 'source_ref', 'raw_text', 'publish_to_podcast', 'generate_short', 'evergreen_fallback'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        $video->update($filtered);

        return $video->fresh() ?? $video;
    }

    public function delete(TvVideo $video): void
    {
        $this->assertNotBusy($video);
        $video->delete();
    }

    public function toggleFlag(TvVideo $video, string $flag): TvVideo
    {
        $this->assertNotBusy($video);

        $allowed = ['publish_to_podcast', 'generate_short', 'evergreen_fallback'];
        if (! in_array($flag, $allowed, true)) {
            throw new InvalidArgumentException("Flag no permitida para toggle: [{$flag}].");
        }

        $video->update([$flag => ! (bool) $video->{$flag}]);

        return $video->fresh() ?? $video;
    }

    /**
     * @return array<string, int>
     */
    public function stageCounts(string $channelSlug = 'ai-today'): array
    {
        $counts = TvVideo::query()
            ->where('channel_slug', $channelSlug)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'queued' => (int) ($counts['queued'] ?? 0),
            'rendering' => (int) ($counts['rendering'] ?? 0),
            'normalizing' => (int) ($counts['normalizing'] ?? 0),
            'ready' => (int) ($counts['ready'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
        ];
    }

    /**
     * @return array{success: bool, job_id?: string, error?: string}
     */
    public function requestNormalization(TvVideo $video, string $profile = '720p30'): array
    {
        $this->assertNotBusy($video);

        $path = trim((string) ($video->relative_media_path ?: $video->file_path ?: ''));
        if ($path === '' || ! str_ends_with(strtolower($path), '.mp4')) {
            throw new InvalidArgumentException("El vídeo [{$video->id}] no tiene una ruta de medios .mp4 válida: [{$path}].");
        }

        try {
            $norm = $this->normalizeAction->enqueue($path, $profile);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $video->update([
            'stage' => 'normalizing',
            'status' => 'normalizing',
            'normalization_job_id' => $norm->id,
            'error_message' => null,
        ]);

        return ['success' => true, 'job_id' => $norm->id];
    }
}
