<?php

namespace App\Livewire\Studio;

use App\Models\Project;
use App\Models\Take;
use App\Services\RoughCutAssemblyService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class RoughCutTimeline extends Component
{
    public Project $project;

    public ?string $filterSceneId = null;

    public ?string $currentPlayingTakeId = null;

    public ?string $renderedVideoUrl = null;

    public function assembleRoughCut(RoughCutAssemblyService $assemblyService): void
    {
        try {
            $result = $assemblyService->assemble($this->project, $this->filterSceneId);
            $this->renderedVideoUrl = $result['video_url'];

            session()->flash('success', "Pre-corte ensamblado con éxito ({$result['total_takes']} tomas, {$result['total_duration_seconds']}s).");
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function mount(Project $project, ?string $activeSceneId = null): void
    {
        $this->project = $project;
        $this->filterSceneId = $activeSceneId;

        $firstGoodTake = $this->getGoodTakes()->first();
        if ($firstGoodTake) {
            $this->currentPlayingTakeId = $firstGoodTake->id;
        }
    }

    /**
     * Obtiene la colección ordenada de tomas aprobadas (is_good_take = true).
     *
     * @return Collection<int, Take>
     */
    public function getGoodTakes(): Collection
    {
        $query = Take::query()
            ->with(['shot.scene.location'])
            ->where('is_good_take', true)
            ->whereHas('shot.scene', function ($q) {
                $q->where('project_id', $this->project->id);
                if ($this->filterSceneId) {
                    $q->where('id', $this->filterSceneId);
                }
            })
            ->join('shots', 'takes.shot_id', '=', 'shots.id')
            ->join('scenes', 'shots.scene_id', '=', 'scenes.id')
            ->orderBy('scenes.scene_order')
            ->orderBy('shots.shot_number')
            ->orderBy('takes.take_number')
            ->select('takes.*');

        return $query->get();
    }

    public function selectTakeForPlayback(string $takeId): void
    {
        $this->currentPlayingTakeId = $takeId;
    }

    public function setSceneFilter(?string $sceneId): void
    {
        $this->filterSceneId = $sceneId ?: null;

        $first = $this->getGoodTakes()->first();
        $this->currentPlayingTakeId = $first?->id;
    }

    /**
     * Exporta la lista de montaje en formato JSON estructurado / EDL.
     */
    public function exportRoughCutJson(): StreamedResponse
    {
        $takes = $this->getGoodTakes();
        $totalDuration = 0.0;
        $cuts = [];

        foreach ($takes as $index => $take) {
            $duration = (float) ($take->technical_meta['duration_seconds'] ?? 5.0);
            $totalDuration += $duration;

            $cuts[] = [
                'sequence_index' => $index + 1,
                'scene_order' => $take->shot?->scene?->scene_order,
                'scene_slugline' => $take->shot?->scene?->slugline,
                'shot_number' => $take->shot?->shot_number,
                'shot_type' => $take->shot?->shot_type,
                'shot_description' => $take->shot?->description,
                'take_id' => $take->id,
                'take_number' => $take->take_number,
                'file_path' => $take->file_path,
                'video_url' => $take->video_url,
                'is_good_take' => $take->is_good_take,
                'editorial_notes' => $take->editorial_notes,
                'technical_meta' => $take->technical_meta ?? [
                    'duration_seconds' => $duration,
                    'resolution' => '1920x1080',
                    'fps' => 24.0,
                    'codec' => 'h264',
                ],
            ];
        }

        $payload = [
            'project' => [
                'id' => $this->project->id,
                'title' => $this->project->title,
            ],
            'exported_at' => now()->toIso8601String(),
            'total_cuts' => count($cuts),
            'total_estimated_duration_seconds' => round($totalDuration, 2),
            'timeline' => $cuts,
        ];

        $filename = 'corte-montaje-'.Str::slug($this->project->title).'-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function render()
    {
        $goodTakes = $this->getGoodTakes();

        $currentTake = null;
        if ($this->currentPlayingTakeId) {
            $currentTake = $goodTakes->firstWhere('id', $this->currentPlayingTakeId)
                ?? Take::with(['shot.scene.location'])->find($this->currentPlayingTakeId);
        }

        $totalEstimatedSeconds = $goodTakes->sum(function (Take $take) {
            return (float) ($take->technical_meta['duration_seconds'] ?? 5.0);
        });

        $scenes = $this->project->scenes()->orderBy('scene_order')->get();

        return view('livewire.studio.rough-cut-timeline', [
            'goodTakes' => $goodTakes,
            'currentTake' => $currentTake,
            'totalEstimatedSeconds' => $totalEstimatedSeconds,
            'scenes' => $scenes,
        ])->layoutData([
            'title' => $this->project->title.' — Montaje / Corte Previo',
            'currentProject' => $this->project,
        ]);
    }
}
