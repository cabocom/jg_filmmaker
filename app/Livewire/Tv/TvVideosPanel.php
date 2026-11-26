<?php

declare(strict_types=1);

namespace App\Livewire\Tv;

use App\Actions\Tv\ManageTvVideoAction;
use App\Actions\Tv\ScanAndSyncTvDriveMediaAction;
use App\Actions\Tv\SyncTvChannelConfigAction;
use App\Models\TvVideo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class TvVideosPanel extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public string $search = '';

    public string $sortField = 'title';

    public string $sortDirection = 'asc';

    public int $perPage = 15;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    protected bool $justInitiated = false;

    public function updated(string $property): void
    {
        if ($property === 'perPage') {
            $this->perPage = in_array($this->perPage, [10, 15, 25, 50, 100], true) ? $this->perPage : 15;
            session(['tv_videos_per_page' => $this->perPage]);
        }

        if (in_array($property, ['search', 'filter', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        $allowed = ['title', 'type', 'stage', 'broadcast_compliant', 'duration_seconds', 'relative_media_path', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortField === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $column;
            $this->sortDirection = $column === 'created_at' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function boot(): void
    {
        if (! (bool) Auth::user()?->is_admin) {
            abort(403);
        }
    }

    public function mount(): void
    {
        if (! (bool) Auth::user()?->is_admin) {
            abort(403);
        }

        $savedPerPage = (int) session('tv_videos_per_page', 15);
        $this->perPage = in_array($savedPerPage, [10, 15, 25, 50, 100], true) ? $savedPerPage : 15;
    }

    public function scanDriveNow(ScanAndSyncTvDriveMediaAction $action): void
    {
        $this->errorMessage = null;
        $this->statusMessage = null;

        $res = $action->execute('ai-today', forceRefresh: true);
        if (! ($res['success'] ?? false)) {
            $this->errorMessage = (string) ($res['error'] ?? 'Error al escanear Drive.');

            return;
        }

        $scanned = (int) ($res['videos_scanned'] ?? 0);
        $nonCompliant = (int) ($res['non_compliant_videos'] ?? 0);
        $this->statusMessage = "Escaneo completado: {$scanned} vídeos analizados ({$nonCompliant} no conformes).";
    }

    public function normalizeVideo(
        string $videoId,
        ManageTvVideoAction $action,
        SyncTvChannelConfigAction $configAction
    ): void {
        $this->errorMessage = null;
        $this->statusMessage = null;

        try {
            $video = TvVideo::query()->where('channel_slug', 'ai-today')->findOrFail($videoId);
            $profile = (string) ($configAction->getOrCreateConfig('ai-today')->broadcast_profile ?: '720p30');
            $res = $action->requestNormalization($video, $profile);

            if (! ($res['success'] ?? false)) {
                $this->errorMessage = (string) ($res['error'] ?? 'Error al solicitar normalización.');

                return;
            }

            $this->justInitiated = true;
            $this->statusMessage = "Normalización solicitada correctamente (Job ID: {$res['job_id']}).";
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleVideoFlag(string $videoId, string $flag, ManageTvVideoAction $action): void
    {
        $this->errorMessage = null;
        $this->statusMessage = null;

        try {
            $video = TvVideo::query()->where('channel_slug', 'ai-today')->findOrFail($videoId);
            $action->toggleFlag($video, $flag);
            $this->statusMessage = "Flag '{$flag}' actualizada.";
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteVideo(string $videoId, ManageTvVideoAction $action): void
    {
        $this->errorMessage = null;
        $this->statusMessage = null;

        try {
            $video = TvVideo::query()->where('channel_slug', 'ai-today')->findOrFail($videoId);
            $action->delete($video);
            $this->statusMessage = 'Vídeo eliminado correctamente.';
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function updateVideoType(string $videoId, string $type, ManageTvVideoAction $action): void
    {
        $this->errorMessage = null;
        $this->statusMessage = null;

        try {
            $video = TvVideo::query()->where('channel_slug', 'ai-today')->findOrFail($videoId);
            $action->update($video, ['type' => $type]);
            $this->statusMessage = "Tipo actualizado a '{$type}'.";
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(SyncTvChannelConfigAction $configAction, ScanAndSyncTvDriveMediaAction $syncAction): View
    {
        $hasBusyVideos = TvVideo::query()->where('channel_slug', 'ai-today')
            ->whereIn('stage', ['queued', 'rendering', 'normalizing'])
            ->exists();

        if ($hasBusyVideos && ! $this->justInitiated) {
            $syncAction->refreshActiveJobsOnly('ai-today');
        }
        $this->justInitiated = false;

        $query = TvVideo::query()->where('channel_slug', 'ai-today');
        if (in_array($this->filter, ['pill', 'news', 'debate', 'music'], true)) {
            $query->where('type', $this->filter);
        } elseif ($this->filter === 'non_compliant') {
            $query->where('broadcast_compliant', false);
        }

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where(function ($sub) use ($like): void {
                $sub->where('title', 'like', $like)
                    ->orWhere('relative_media_path', 'like', $like);
            });
        }

        $allowedSorts = ['title', 'type', 'stage', 'broadcast_compliant', 'duration_seconds', 'relative_media_path', 'created_at'];
        $sortCol = in_array($this->sortField, $allowedSorts, true) ? $this->sortField : 'title';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';
        $videos = $query->orderBy($sortCol, $sortDir)->latest('created_at')->paginate($this->perPage);
        $stillBusy = TvVideo::query()->where('channel_slug', 'ai-today')
            ->whereIn('stage', ['queued', 'rendering', 'normalizing'])
            ->exists();

        return view('livewire.tv.videos-panel', [
            'videos' => $videos,
            'channelConfig' => $configAction->getOrCreateConfig('ai-today'),
            'hasBusyVideos' => $stillBusy,
            'nonCompliantVideos' => TvVideo::query()->where('channel_slug', 'ai-today')->where('broadcast_compliant', false)->orderBy('title')->get(),
        ]);
    }
}
