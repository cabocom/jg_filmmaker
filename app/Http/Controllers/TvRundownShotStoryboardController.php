<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shot;
use App\Models\TvRundown;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TvRundownShotStoryboardController extends Controller
{
    public function __invoke(
        Request $request,
        TvRundown $rundown,
        Shot $shot,
        MediaStorageService $mediaStorage,
    ): StreamedResponse|Response|RedirectResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $rundown->loadMissing('broadcastJob', 'project');
        $shot->loadMissing('scene');

        $jobBlockId = $rundown->broadcastJob?->script_block_id;
        $belongsToBlock = $jobBlockId !== null
            && (string) $shot->script_block_id === (string) $jobBlockId;
        $belongsToProject = $rundown->project_id !== null
            && (string) ($shot->scene?->project_id ?? '') === (string) $rundown->project_id;

        if (! $belongsToBlock && ! $belongsToProject) {
            abort(404, 'El plano no pertenece a la escaleta.');
        }

        return $mediaStorage->serve(
            $shot->storyboard_image_path,
            'Viñeta de storyboard no encontrada.',
            ['Cache-Control' => 'private, no-cache, must-revalidate'],
        );
    }
}
