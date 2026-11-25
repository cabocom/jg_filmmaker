<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Broadcast\NormalizeBroadcastMediaAction;
use App\Actions\Broadcast\ScanTvMediaDirectoryAction;
use App\Actions\Broadcast\StreamTvMediaAction;
use App\Http\Controllers\Controller;
use App\Models\TvMediaNormalization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BroadcastMediaController extends Controller
{
    public function scan(Request $request, ScanTvMediaDirectoryAction $action): JsonResponse
    {
        $validated = $request->validate([
            'relative_dir' => ['nullable', 'string', 'max:300'],
            'profile' => ['nullable', 'string', 'in:720p30,1080p30'],
            'force_refresh' => ['sometimes', 'boolean'],
        ]);

        try {
            $relativeDir = (string) ($validated['relative_dir'] ?? '');
            $profile = (string) ($validated['profile'] ?? '720p30');
            $forceRefresh = (bool) ($validated['force_refresh'] ?? false);

            $result = $action->execute($relativeDir, $profile, $forceRefresh);

            return response()->json($result, 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function normalize(Request $request, NormalizeBroadcastMediaAction $action): JsonResponse
    {
        $validated = $request->validate([
            'relative_path' => ['required', 'string', 'max:500'],
            'profile' => ['nullable', 'string', 'in:720p30,1080p30'],
        ]);

        try {
            $profile = (string) ($validated['profile'] ?? '720p30');
            $norm = $action->enqueue($validated['relative_path'], $profile);

            return response()->json([
                'job_id' => $norm->id,
                'relative_path' => $norm->relative_path,
                'target_profile' => $norm->target_profile,
                'stage' => $norm->stage,
            ], 202);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            $status = $e->getCode() === 409 ? 409 : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function showNormalization(string $jobId): JsonResponse
    {
        $norm = TvMediaNormalization::find($jobId);

        if ($norm === null) {
            return response()->json(['error' => 'Media normalization job not found'], 404);
        }

        return response()->json([
            'job_id' => $norm->id,
            'relative_path' => $norm->relative_path,
            'target_profile' => $norm->target_profile,
            'stage' => $norm->stage,
            'error_message' => $norm->error_message,
            'probe_before' => $norm->probe_before,
            'probe_after' => $norm->probe_after,
        ], 200);
    }

    public function stream(Request $request, StreamTvMediaAction $action): Response|StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        try {
            $rangeHeader = $request->header('Range');

            return $action->execute((string) $validated['path'], is_string($rangeHeader) ? $rangeHeader : null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            $status = $e->getCode() === 404 ? 404 : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
