<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Project;
use App\Models\Shot;
use App\Models\User;
use App\Services\StoryboardService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

final readonly class ManageStoryboardAction
{
    /**
     * Actualiza el estilo global de storyboard del proyecto.
     */
    public function updateProjectStyle(Project $project, string $style): bool
    {
        if (! array_key_exists($style, StoryboardService::STYLES)) {
            return false;
        }

        $project->update(['storyboard_style' => $style]);

        return true;
    }

    /**
     * Construye el prompt cinemático para la generación del storyboard.
     */
    public function buildPrompt(
        Shot $shot,
        string $style,
        ?string $characterId = null,
        ?string $locationId = null,
        int $propCount = 0,
        ?StoryboardService $storyboardService = null
    ): string {
        $storyboardService ??= app(StoryboardService::class);

        return $storyboardService->buildPrompt(
            shot: $shot,
            style: $style,
            characterId: $characterId,
            locationId: $locationId,
            propCount: $propCount
        );
    }

    /**
     * Ejecuta la generación del storyboard mediante el servicio de IA.
     *
     * @param  list<UploadedFile>  $propImages
     * @return array{success: bool, flash_type: string, message: string}
     */
    public function generate(
        Shot $shot,
        string $style,
        string $prompt,
        ?string $characterId = null,
        ?string $locationId = null,
        array $propImages = [],
        ?User $user = null,
        ?StoryboardService $storyboardService = null
    ): array {
        $user ??= auth()->user() ?? $shot->scene?->project?->user;
        $storyboardService ??= app(StoryboardService::class);

        $quotaError = $this->checkTokenQuota($user);
        if ($quotaError !== null) {
            return $quotaError;
        }

        try {
            $storyboardService->generateStoryboard($shot, $style, $prompt, $characterId, $locationId, $propImages);
            $shot->scene?->project?->update(['storyboard_style' => $style]);

            return [
                'success' => true,
                'flash_type' => 'success',
                'message' => "Viñeta de storyboard para el Shot #{$shot->shot_number} generada correctamente con Google AI Studio.",
            ];
        } catch (\Throwable $e) {
            Log::error("Error al generar storyboard para el shot {$shot->id}: ".$e->getMessage());

            return ['success' => false, 'flash_type' => 'error', 'message' => 'No se pudo generar el storyboard: '.$e->getMessage()];
        }
    }

    private function checkTokenQuota(?User $user): ?array
    {
        if ($user && ! $user->hasDailyTokensAvailable(350)) {
            $available = $user->getTokensAvailableToday();
            $limit = $user->getDailyTokenLimit();

            return [
                'success' => false,
                'flash_type' => 'error',
                'message' => "Saldo insuficiente de tokens para generar la viñeta ({$available} disponibles, ~350 requeridos de tu cupo de ".number_format($limit, 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.',
            ];
        }

        return null;
    }
}
