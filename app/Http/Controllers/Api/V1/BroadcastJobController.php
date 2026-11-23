<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcast\StoreBroadcastJobRequest;
use App\Models\BroadcastJob;
use Illuminate\Http\JsonResponse;

class BroadcastJobController extends Controller
{
    /**
     * Create and initialize a new broadcast job pipeline.
     */
    public function store(StoreBroadcastJobRequest $request, AutoGenerateBroadcastShotsAction $action): JsonResponse
    {
        $job = $action->initialize($request->toDto());

        return response()->json([
            'job_id' => $job->id,
            'channel_id' => $job->channel_id,
            'format' => $job->format,
            'stage' => $job->stage->value,
            'status' => $job->stage->value,
            'project_id' => $job->project_id,
            'script_block_id' => $job->script_block_id,
        ], 202);
    }

    /**
     * Retrieve status, stage and artifacts of an existing broadcast job.
     */
    public function show(string $jobId): JsonResponse
    {
        $job = BroadcastJob::find($jobId);

        if ($job === null) {
            return response()->json([
                'error' => 'Broadcast job not found',
            ], 404);
        }

        return response()->json([
            'job_id' => $job->id,
            'channel_id' => $job->channel_id,
            'format' => $job->format,
            'title' => $job->title,
            'language' => $job->language,
            'stage' => $job->stage->value,
            'status' => $job->stage->value,
            'project_id' => $job->project_id,
            'script_block_id' => $job->script_block_id,
            'artifacts' => $job->artifacts,
            'failed_stage' => $job->failed_stage,
            'error_message' => $job->error_message,
            'created_at' => $job->created_at,
            'updated_at' => $job->updated_at,
        ], 200);
    }
}
