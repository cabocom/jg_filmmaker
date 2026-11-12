<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\Location;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class SluglineSyncService
{
    /**
     * @return array{type: string, name: string, time_of_day: string}
     */
    public function parseSlugline(string $slugline): array
    {
        $slugline = trim($slugline);
        if (empty($slugline)) {
            return ['type' => 'INT', 'name' => 'LOCALIZACIÓN', 'time_of_day' => 'DÍA'];
        }

        if (preg_match('/^(INT\/EXT|INT\.\/EXT\.|INT|EXT|I\/E)\.?\s*(.*?)(?:\s*[-–—]\s*([^\d\n\r]+?))?(?:\s+\d+)?$/iu', $slugline, $matches)) {
            return $this->parseStandardSluglineMatches($matches);
        }

        return $this->parseFallbackSlugline($slugline);
    }

    protected function parseStandardSluglineMatches(array $matches): array
    {
        $rawType = strtoupper(trim($matches[1]));
        $type = (str_contains($rawType, 'EXT') && str_contains($rawType, 'INT'))
            ? 'INT/EXT'
            : (str_contains($rawType, 'EXT') ? 'EXT' : 'INT');

        $name = trim($matches[2] ?? '');
        $name = trim(preg_replace('/^[-–—\.\s]+|[-–—\.\s]+$/u', '', $name));
        $name = mb_strtoupper($name, 'UTF-8') ?: 'LOCALIZACIÓN';

        $timeOfDay = ! empty($matches[3]) ? strtoupper(trim($matches[3])) : 'DÍA';

        return ['type' => $type, 'name' => $name, 'time_of_day' => $timeOfDay ?: 'DÍA'];
    }

    protected function parseFallbackSlugline(string $slugline): array
    {
        $parts = preg_split('/[-–—]/u', $slugline, 2);
        $headerPart = trim($parts[0] ?? '');
        $timePart = trim($parts[1] ?? '');

        if (preg_match('/^(INT\/EXT|INT|EXT)/i', $headerPart, $tm)) {
            $rawType = strtoupper($tm[1]);
            $type = (str_contains($rawType, 'EXT') && str_contains($rawType, 'INT')) ? 'INT/EXT' : $rawType;
            $name = trim(preg_replace('/^(INT\/EXT|INT|EXT)[\.\s]*/i', '', $headerPart));
        } else {
            $type = 'INT';
            $name = $headerPart;
        }

        $timeOfDay = ! empty($timePart) ? strtoupper(trim(preg_replace('/\s+\d+$/', '', $timePart))) : 'DÍA';
        $name = mb_strtoupper($name, 'UTF-8') ?: 'LOCALIZACIÓN';

        return ['type' => $type, 'name' => $name, 'time_of_day' => $timeOfDay ?: 'DÍA'];
    }

    public function syncProjectLocations(Project $project): void
    {
        if (! $project->exists) {
            return;
        }

        $scenes = $project->scenes()->orderBy('scene_order')->get();
        if ($scenes->isEmpty()) {
            return;
        }

        /** @var Collection<int, Location> $locations */
        $locations = $project->locations()->get();
        $locMap = [];
        foreach ($locations as $loc) {
            $locMap[mb_strtoupper($loc->name, 'UTF-8')] = $loc;
        }

        foreach ($scenes as $scene) {
            $this->syncSceneLocation($scene, $project, $locMap);
        }

        $project->load('locations');
    }

    protected function syncSceneLocation($scene, Project $project, array &$locMap): void
    {
        if (empty($scene->slugline)) {
            return;
        }

        $parsed = $this->parseSlugline($scene->slugline);
        $parsedName = $parsed['name'];
        $parsedType = $parsed['type'];
        $parsedTime = $parsed['time_of_day'];

        if (empty($scene->time_of_day) && ! empty($parsedTime)) {
            $scene->update(['time_of_day' => $parsedTime]);
        }

        $targetLoc = $locMap[$parsedName] ?? null;
        if (! $targetLoc) {
            $targetLoc = $project->locations()->create([
                'name' => $parsedName,
                'type' => $parsedType,
                'art_department_notes' => 'Localización detectada automáticamente en el guión.',
            ]);
            $locMap[$parsedName] = $targetLoc;
        }

        if ($scene->location_id !== $targetLoc->id) {
            $scene->update(['location_id' => $targetLoc->id]);
        }
    }
}
