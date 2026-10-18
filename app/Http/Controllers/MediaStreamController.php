<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Take;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController extends Controller
{
    /**
     * Stream a take video file safely with tenant ownership authorization.
     */
    public function streamTake(Take $take): StreamedResponse|Response
    {
        $project = $take->shot?->scene?->project;

        if (! $project) {
            abort(404, 'Proyecto no encontrado para esta toma.');
        }

        $user = Auth::user();
        if (! $user || ($project->user_id !== $user->id && ! $user->is_admin)) {
            abort(403, 'No autorizado para acceder a este contenido audiovisual.');
        }

        if (empty($take->file_path)) {
            abort(404, 'Toma sin archivo asociado.');
        }

        if (Storage::disk('local')->exists($take->file_path)) {
            return Storage::disk('local')->response($take->file_path);
        }

        if (Storage::disk('public')->exists($take->file_path)) {
            return Storage::disk('public')->response($take->file_path);
        }

        abort(404, 'Archivo audiovisual no encontrado.');
    }

    /**
     * Stream a project render file safely with tenant ownership authorization.
     */
    public function streamRender(Project $project, string $filename): StreamedResponse|Response
    {
        $user = Auth::user();
        if (! $user || ($project->user_id !== $user->id && ! $user->is_admin)) {
            abort(403, 'No autorizado para acceder a este render.');
        }

        $cleanFilename = basename($filename);
        $relativePath = "media/projects/{$project->id}/renders/{$cleanFilename}";

        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->response($relativePath);
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->response($relativePath);
        }

        abort(404, 'Archivo de render no encontrado.');
    }
}
