<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Actions\Broadcast\NormalizeBroadcastMediaAction;
use App\Models\TvAdSlot;
use App\Services\Broadcast\TvMediaPathResolver;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ManageTvAdSlotAction
{
    public const array ALLOWED_ROLES = ['ad', 'fallback_l3', 'intro', 'outro'];

    public function __construct(
        private readonly NormalizeBroadcastMediaAction $normalizeAction,
        private readonly TvMediaPathResolver $pathResolver,
    ) {}

    private function assertValidRole(mixed $role): string
    {
        if (! is_string($role) || ! in_array($role, self::ALLOWED_ROLES, true)) {
            $formatted = is_scalar($role) ? (string) $role : gettype($role);
            throw new InvalidArgumentException("Rol de activo publicitario inválido [{$formatted}].");
        }

        return $role;
    }

    public function assertNotBusy(TvAdSlot $slot): void
    {
        if ($slot->isBusy()) {
            throw new RuntimeException("El slot publicitario [{$slot->id}] está en proceso ({$slot->stage}) y no puede modificarse.", 409);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TvAdSlot
    {
        $role = $this->assertValidRole($data['asset_role'] ?? 'ad');
        $rawPath = (string) ($data['media_path'] ?? '');
        $mediaPath = $rawPath !== ''
            ? $this->pathResolver->normalizeRelativePath($rawPath, requireMp4Extension: false, allowEmpty: true)
            : '';
        $title = (string) ($data['title'] ?? $data['name'] ?? '');

        return TvAdSlot::create([
            'channel_slug' => (string) ($data['channel_slug'] ?? 'ai-today'),
            'asset_role' => $role,
            'name' => $title,
            'title' => $title,
            'media_path' => $mediaPath,
            'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
            'active' => (bool) ($data['active'] ?? true),
            'is_active' => (bool) ($data['active'] ?? true),
            'frequency_minutes' => (int) ($data['frequency_minutes'] ?? 0),
            'paid_promotion' => (bool) ($data['paid_promotion'] ?? false),
            'stage' => 'ready',
        ]);
    }

    public function toggleActive(TvAdSlot $slot): TvAdSlot
    {
        $this->assertNotBusy($slot);
        $next = ! (bool) $slot->active;
        $slot->update(['active' => $next, 'is_active' => $next]);

        return $slot;
    }

    public function delete(TvAdSlot $slot): void
    {
        $this->assertNotBusy($slot);
        $slot->delete();
    }

    /**
     * @return array{success: bool, job_id?: string, error?: string}
     */
    public function requestNormalization(TvAdSlot $slot, string $profile = '720p30'): array
    {
        $this->assertNotBusy($slot);

        $mediaPath = trim((string) $slot->media_path);
        if ($mediaPath === '' || ! str_ends_with(strtolower($mediaPath), '.mp4')) {
            throw new InvalidArgumentException("El slot publicitario [{$slot->id}] no tiene una ruta de medios .mp4 válida: [{$mediaPath}].");
        }

        try {
            $norm = $this->normalizeAction->enqueue($mediaPath, $profile);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $slot->update([
            'stage' => 'normalizing',
            'normalization_job_id' => $norm->id,
            'error_message' => null,
        ]);

        return ['success' => true, 'job_id' => $norm->id];
    }
}
