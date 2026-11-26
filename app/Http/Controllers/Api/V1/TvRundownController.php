<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tv\ManageTvRundownAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tv\StoreTvRundownApiRequest;
use App\Models\TvRundown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TvRundownController extends Controller
{
    /**
     * List TV rundowns with optional status/format filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));
        $status = $request->query('status');
        $format = $request->query('format');

        $query = TvRundown::query()
            ->with(['tvVideo', 'broadcastJob'])
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        if (is_string($format) && $format !== '') {
            $query->where('format', $format);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (TvRundown $rundown): array => $this->serializeRundown($rundown))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Create a new TV rundown via M2M API.
     */
    public function store(StoreTvRundownApiRequest $request, ManageTvRundownAction $action): JsonResponse
    {
        $rundown = $action->createFromPromptOrSource($request->validated());

        if (
            $request->boolean('dispatch_production')
            && $rundown->source_type !== 'wikipedia'
            && trim((string) $rundown->script_text) !== ''
        ) {
            $rundown = $action->dispatchBroadcastProduction($rundown);
        }

        $rundown->load(['tvVideo', 'broadcastJob']);

        return response()->json($this->serializeRundown($rundown), 201);
    }

    /**
     * Retrieve a single TV rundown by UUID.
     */
    public function show(string $rundownId): JsonResponse
    {
        $rundown = TvRundown::query()
            ->with(['tvVideo', 'broadcastJob'])
            ->find($rundownId);

        if ($rundown === null) {
            return response()->json(['error' => 'TV rundown not found'], 404);
        }

        return response()->json($this->serializeRundown($rundown), 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRundown(TvRundown $rundown): array
    {
        return [
            'id' => $rundown->id,
            'project_id' => $rundown->project_id,
            'channel_config_id' => $rundown->channel_config_id,
            'day_schedule_id' => $rundown->day_schedule_id,
            'broadcast_job_id' => $rundown->broadcast_job_id,
            'title' => $rundown->title,
            'topic_or_url' => $rundown->topic_or_url,
            'source_type' => $rundown->source_type,
            'format' => $rundown->format,
            'language' => $rundown->language,
            'script_text' => $rundown->script_text,
            'visual_credit' => $rundown->visual_credit,
            'sources' => $rundown->sources ?? [],
            'tts_provider' => $rundown->tts_provider,
            'tts_voice' => $rundown->tts_voice,
            'visual_mode' => $rundown->visual_mode,
            'resolution_profile' => $rundown->resolution_profile,
            'status' => $rundown->status,
            'duration_seconds' => $rundown->duration_seconds,
            'sort_order' => $rundown->sort_order,
            'error_message' => $rundown->error_message,
            'scheduled_at' => $rundown->scheduled_at,
            'published_at' => $rundown->published_at,
            'tv_video' => $rundown->tvVideo !== null ? [
                'id' => $rundown->tvVideo->id,
                'status' => $rundown->tvVideo->status,
                'file_path' => $rundown->tvVideo->file_path,
                'podcast_path' => $rundown->tvVideo->podcast_path,
                'short_path' => $rundown->tvVideo->short_path,
                'duration_seconds' => $rundown->tvVideo->duration_seconds,
            ] : null,
            'created_at' => $rundown->created_at,
            'updated_at' => $rundown->updated_at,
        ];
    }
}
