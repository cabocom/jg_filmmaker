<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Broadcast\StreamTvMediaAction;
use App\Models\TvVideo;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TvVideoStreamController extends Controller
{
    public function __invoke(Request $request, TvVideo $video, StreamTvMediaAction $streamAction): Response|StreamedResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $path = trim((string) ($video->relative_media_path ?: $video->file_path ?: $video->mp4_url ?: ''));
        if ($path === '') {
            abort(404, 'Vídeo sin ruta multimedia asociada.');
        }

        try {
            return $streamAction->execute($path, $request->header('Range'));
        } catch (RuntimeException $e) {
            abort($e->getCode() === 404 ? 404 : 500, $e->getMessage());
        }
    }
}
