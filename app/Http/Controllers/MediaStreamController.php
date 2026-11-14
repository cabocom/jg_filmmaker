<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Shot;
use App\Models\Take;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController extends Controller
{
    /**
     * Stream a take video file safely with tenant ownership authorization.
     */
    public function streamTake(Take $take): StreamedResponse|Response
    {
        $this->authorizeProjectAccess($take->shot?->scene?->project);

        return $this->serveFile($take->file_path, 'Toma sin archivo asociado.');
    }

    /**
     * Stream a project render file safely with tenant ownership authorization.
     */
    public function streamRender(Project $project, string $filename): StreamedResponse|Response
    {
        $this->authorizeProjectAccess($project);

        $cleanFilename = basename($filename);
        $relativePath = "media/projects/{$project->id}/renders/{$cleanFilename}";

        return $this->serveFile($relativePath, 'Archivo de render no encontrado.');
    }

    /**
     * Stream a shot storyboard panel image safely with tenant authorization.
     */
    public function streamStoryboard(Shot $shot): StreamedResponse|Response
    {
        $this->authorizeProjectAccess($shot->scene?->project);

        return $this->serveFile($shot->storyboard_image_path, 'Viñeta de storyboard no encontrada.');
    }

    /**
     * Stream a character reference turn-around image safely with tenant authorization.
     */
    public function streamCharacter(Character $character): StreamedResponse|Response
    {
        $this->authorizeProjectAccess($character->project);

        return $this->serveFile($character->reference_image_path, 'Imagen de referencia de personaje no encontrada.');
    }

    /**
     * Stream a location reference image safely with tenant authorization.
     */
    public function streamLocation(Location $location): StreamedResponse|Response
    {
        $this->authorizeProjectAccess($location->project);

        return $this->serveFile($location->reference_image_path, 'Imagen de referencia de localización no encontrada.');
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

    /**
     * Sirve el archivo comprobando disco local primero y público con cabeceras privadas.
     *
     * @param  array<string, string>  $headers
     */
    protected function serveFile(?string $path, string $notFoundMessage = 'Archivo no encontrado.', array $headers = []): StreamedResponse|Response
    {
        if (empty($path)) {
            abort(404, $notFoundMessage);
        }

        $allHeaders = array_merge(['Cache-Control' => 'private, max-age=3600'], $headers);

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path, null, $allHeaders);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, null, $allHeaders);
        }

        abort(404, $notFoundMessage);
    }
}
