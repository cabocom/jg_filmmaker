<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Models\BroadcastJob;
use App\Models\Location;
use App\Models\Project;
use App\Services\Pipeline\BibleSyncService;
use App\Services\Pipeline\SluglineSyncService;
use Illuminate\Support\Facades\Log;

final readonly class SyncBroadcastProjectBibleAction
{
    public function __construct(
        protected BibleSyncService $bibleSync,
        protected SluglineSyncService $sluglineSync,
    ) {}

    /**
     * Sincroniza la Biblia del Proyecto (personajes y localizaciones) para un BroadcastJob.
     *
     * @return array{characters_count: int, locations_count: int, characters: list<string>, locations: list<string>}
     */
    public function execute(BroadcastJob $job, ?Project $project = null): array
    {
        $project = $project ?? $job->project;
        if (! $project) {
            return ['characters_count' => 0, 'locations_count' => 0, 'characters' => [], 'locations' => []];
        }

        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];

        $this->seedCastProfiles($project, $artifacts['cast_profiles'] ?? []);
        $this->syncLocations($project, $artifacts['location_profile'] ?? null);

        try {
            $this->bibleSync->syncProjectCharacters($project);
        } catch (\Throwable $e) {
            Log::warning('SyncBroadcastProjectBibleAction: character extraction failed', [
                'broadcast_job_id' => $job->id,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }

        $formatLower = strtolower(trim((string) $job->format));
        if ($project->characters()->count() === 0 && ! in_array($formatLower, ['pill', 'pildora'], true)) {
            $this->seedDefaultPresenter($project, (string) $job->format);
        }

        $project->load(['characters', 'locations']);

        return [
            'characters_count' => $project->characters->count(),
            'locations_count' => $project->locations->count(),
            'characters' => $project->characters->pluck('name')->values()->all(),
            'locations' => $project->locations->pluck('name')->values()->all(),
        ];
    }

    private function seedCastProfiles(Project $project, mixed $profiles): void
    {
        if (! is_array($profiles) || $profiles === []) {
            return;
        }

        $existingLower = $project->characters()
            ->pluck('name')
            ->map(fn (mixed $name): string => mb_strtolower(trim((string) $name), 'UTF-8'))
            ->all();

        foreach ($profiles as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $name = trim((string) ($profile['name'] ?? ''));
            $lower = mb_strtolower($name, 'UTF-8');
            if ($name === '' || in_array($lower, $existingLower, true)) {
                continue;
            }

            $project->characters()->create([
                'name' => $name,
                'bio' => isset($profile['bio']) ? (string) $profile['bio'] : null,
                'psychology' => isset($profile['psychology']) ? (string) $profile['psychology'] : null,
                'costume_notes' => isset($profile['costume_notes']) ? (string) $profile['costume_notes'] : null,
            ]);
            $existingLower[] = $lower;
        }
    }

    private function syncLocations(Project $project, mixed $locationProfile): void
    {
        $this->sluglineSync->syncProjectLocations($project);

        if (! is_array($locationProfile) || $locationProfile === []) {
            return;
        }

        $primaryScene = $project->scenes()->orderBy('scene_order')->first();
        $name = trim((string) ($locationProfile['name'] ?? ''));

        $targetLocation = $name !== ''
            ? $project->locations()->get()->first(
                fn (Location $loc): bool => mb_strtolower($loc->name, 'UTF-8') === mb_strtolower($name, 'UTF-8')
            )
            : $primaryScene?->location;

        $attributes = array_filter([
            'type' => isset($locationProfile['type']) ? (string) $locationProfile['type'] : null,
            'art_department_notes' => isset($locationProfile['art_department_notes']) ? (string) $locationProfile['art_department_notes'] : null,
            'lighting_notes' => isset($locationProfile['lighting_notes']) ? (string) $locationProfile['lighting_notes'] : null,
            'address_reference' => isset($locationProfile['address_reference']) ? (string) $locationProfile['address_reference'] : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($targetLocation) {
            if ($attributes !== []) {
                $targetLocation->update($attributes);
            }
        } else {
            $primarySlugline = strtoupper(trim((string) ($primaryScene?->slugline ?? '')));
            $defaultLocationName = (str_contains($primarySlugline, 'DOCUMENTAL') || str_contains($primarySlugline, 'EXT.'))
                ? 'DOCUMENTAL TEMÁTICO'
                : 'ESTUDIO BROADCAST';

            $targetLocation = $project->locations()->create(array_merge([
                'name' => $name !== '' ? $name : $defaultLocationName,
                'type' => str_contains($primarySlugline, 'EXT.') ? 'EXT' : 'INT',
            ], $attributes));
        }

        if ($primaryScene && $primaryScene->location_id !== $targetLocation->id) {
            $primaryScene->update(['location_id' => $targetLocation->id]);
        }
    }

    private function seedDefaultPresenter(Project $project, string $format): void
    {
        $project->characters()->create([
            'name' => 'Presentador',
            'bio' => "Presentador principal de emisión broadcast ({$format}), con tono cercano, claro y profesional.",
            'costume_notes' => 'Vestuario sobrio de plató: americana estructurada en tonos neutros y camisa limpia.',
        ]);
    }
}
