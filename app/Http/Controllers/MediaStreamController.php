<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Shot;
use App\Models\Take;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController extends Controller
{
    public function __construct(
        protected ?MediaStorageService $mediaStorage = null
    ) {
        $this->mediaStorage = $this->mediaStorage ?? app(MediaStorageService::class);
    }

    /**
     * Stream a take video file safely with tenant ownership authorization.
     */
    public function streamTake(Take $take): StreamedResponse|Response|RedirectResponse
    {
        $this->authorizeProjectAccess($take->shot?->scene?->project);

        return $this->mediaStorage->serve($take->effectiveVideoPath() ?? $take->file_path, 'Toma sin archivo asociado.');
    }

    /**
     * Stream a project render file safely with tenant ownership authorization.
     */
    public function streamRender(Project $project, string $filename): StreamedResponse|Response|RedirectResponse
    {
        $this->authorizeProjectAccess($project);

        $cleanFilename = basename($filename);
        $relativePath = "media/projects/{$project->id}/renders/{$cleanFilename}";

        return $this->mediaStorage->serve($relativePath, 'Archivo de render no encontrado.');
    }

    /**
     * Stream a shot storyboard panel image safely with tenant authorization.
     */
    public function streamStoryboard(Shot $shot): StreamedResponse|Response|RedirectResponse
    {
        $this->authorizeProjectAccess($shot->scene?->project);

        return $this->mediaStorage->serve($shot->storyboard_image_path, 'Viñeta de storyboard no encontrada.');
    }

    /**
     * Stream a character reference turn-around image safely with tenant authorization.
     */
    public function streamCharacter(Character $character): StreamedResponse|Response|RedirectResponse
    {
        $this->authorizeProjectAccess($character->project);

        return $this->mediaStorage->serve($character->reference_image_path, 'Imagen de referencia de personaje no encontrada.');
    }

    /**
     * Stream a location reference image safely with tenant authorization.
     */
    public function streamLocation(Location $location): StreamedResponse|Response|RedirectResponse
    {
        $this->authorizeProjectAccess($location->project);

        return $this->mediaStorage->serve($location->reference_image_path, 'Imagen de referencia de localización no encontrada.');
    }

    /**
     * Valida que el usuario autenticado tenga permiso de visualización del proyecto mediante ProjectPolicy.
     * Política de privacidad estricta: los administradores NO tienen acceso al material creativo ajeno.
     */
    protected function authorizeProjectAccess(?Project $project): void
    {
        if (! $project) {
            abort(404, 'Proyecto no encontrado.');
        }

        Gate::authorize('view', $project);
    }
}
