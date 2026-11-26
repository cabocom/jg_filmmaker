<?php

declare(strict_types=1);

namespace App\Livewire\Tv;

use App\Actions\Tv\ManageTvAdSlotAction;
use App\Actions\Tv\ScanAndSyncTvDriveMediaAction;
use App\Actions\Tv\SyncTvChannelConfigAction;
use App\Models\TvAdSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;

class TvAdsPanel extends Component
{
    public string $title = '';

    public string $assetRole = 'ad';

    public string $adMediaPath = '';

    public int $durationSeconds = 0;

    public int $frequencyMinutes = 15;

    public bool $paidPromotion = false;

    public string $statusMessage = '';

    public string $errorMessage = '';

    protected bool $justInitiated = false;

    /** @var array<int, string> */
    public array $availableAdFiles = [];

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
    }

    public function scanAdsFolder(ScanAndSyncTvDriveMediaAction $action): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';

        $res = $action->execute('ai-today', forceRefresh: true);
        if (! ($res['success'] ?? false)) {
            $this->errorMessage = (string) ($res['error'] ?? 'Error al escanear carpeta de cuñas.');

            return;
        }

        $this->availableAdFiles = is_array($res['available_ad_files'] ?? null)
            ? array_values($res['available_ad_files'])
            : [];
        $this->statusMessage = 'Carpeta de cuñas escaneada correctamente.';
    }

    public function saveAdSlot(ManageTvAdSlotAction $action): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';

        $this->validate([
            'title' => 'required|string|max:255',
            'assetRole' => 'required|string|in:ad,fallback_l3,intro,outro',
            'adMediaPath' => 'required|string|max:500',
            'durationSeconds' => 'required|integer|min:0',
            'frequencyMinutes' => 'nullable|integer|min:0',
            'paidPromotion' => 'boolean',
        ]);

        try {
            $action->create([
                'channel_slug' => 'ai-today',
                'title' => $this->title,
                'asset_role' => $this->assetRole,
                'media_path' => $this->adMediaPath,
                'duration_seconds' => $this->durationSeconds,
                'frequency_minutes' => $this->frequencyMinutes,
                'paid_promotion' => $this->paidPromotion,
            ]);

            $this->reset(['title', 'adMediaPath', 'durationSeconds', 'paidPromotion']);
            $this->statusMessage = 'Slot publicitario guardado con éxito.';
        } catch (InvalidArgumentException $e) {
            $this->addError('adMediaPath', $e->getMessage());
        }
    }

    public function normalizeAdSlot(
        string $slotId,
        ManageTvAdSlotAction $action,
        SyncTvChannelConfigAction $configAction
    ): void {
        $this->errorMessage = '';
        $this->statusMessage = '';

        $profile = (string) ($configAction->getOrCreateConfig('ai-today')->broadcast_profile ?: '720p30');
        $slot = TvAdSlot::query()->where('channel_slug', 'ai-today')->findOrFail($slotId);

        try {
            $res = $action->requestNormalization($slot, $profile);
            if (! ($res['success'] ?? false)) {
                $this->errorMessage = (string) ($res['error'] ?? 'Error al solicitar normalización.');

                return;
            }
            $this->justInitiated = true;
            $this->statusMessage = "Normalización solicitada para el slot [{$slotId}].";
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleAdActive(string $slotId, ManageTvAdSlotAction $action): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';

        try {
            $slot = TvAdSlot::query()->where('channel_slug', 'ai-today')->findOrFail($slotId);
            $action->toggleActive($slot);
            $this->statusMessage = 'Estado del slot publicitario actualizado.';
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteAdSlot(string $slotId, ManageTvAdSlotAction $action): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';

        try {
            $slot = TvAdSlot::query()->where('channel_slug', 'ai-today')->findOrFail($slotId);
            $action->delete($slot);
            $this->statusMessage = 'Slot publicitario eliminado correctamente.';
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(
        SyncTvChannelConfigAction $configAction,
        ScanAndSyncTvDriveMediaAction $syncAction
    ): View {
        $busyCount = TvAdSlot::query()
            ->where('channel_slug', 'ai-today')
            ->whereIn('stage', ['queued', 'normalizing'])
            ->count();

        if ($busyCount > 0 && ! $this->justInitiated) {
            $syncAction->refreshActiveJobsOnly('ai-today');
        }
        $this->justInitiated = false;

        $adSlots = TvAdSlot::query()
            ->where('channel_slug', 'ai-today')
            ->latest()
            ->get();

        $hasBusyAds = $adSlots->contains(fn (TvAdSlot $s): bool => $s->isBusy());
        $channelConfig = $configAction->getOrCreateConfig('ai-today');

        return view('livewire.tv.ads-panel', [
            'channelConfig' => $channelConfig,
            'adSlots' => $adSlots,
            'hasBusyAds' => $hasBusyAds,
        ]);
    }
}
