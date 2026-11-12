<?php

declare(strict_types=1);

namespace App\Services\Storyboard;

use App\Models\Character;
use App\Models\Location;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Collection;

class StoryboardPromptBuilder
{
    /**
     * @param  array<string, array{name: string, badge: string, description: string, modifier: string}>  $styles
     */
    public function __construct(
        protected array $styles
    ) {}

    public function buildPrompt(
        Shot $shot,
        ?string $style = null,
        ?string $characterId = null,
        ?string $locationId = null,
        int $propCount = 0
    ): string {
        $shot->loadMissing(['scene.project.characters', 'scene.project.locations', 'scene.location', 'scriptBlock']);

        $styleKey = $style ?? $shot->scene?->project?->storyboard_style ?? 'grayscale_sketch';
        $styleData = $this->styles[$styleKey] ?? $this->styles['grayscale_sketch'];

        $framing = $shot->shot_type ?: 'Medium Shot';
        $sceneHeader = $shot->scene ? "ESCENA {$shot->scene->scene_order}: {$shot->scene->slugline}" : 'SCENE';
        $description = trim((string) $shot->description) ?: 'Cinematic composition depicting the scene action.';

        $locationDirective = $this->resolveLocationConditioning($shot, $locationId);
        $character = $this->resolveCharacterModel($shot, $characterId);
        $charTag = $character ? '@'.preg_replace('/\s+/', '_', trim($character->name)) : '@personaje';
        $characterDirective = $this->resolveCharacterConditioning($shot, $characterId);

        if ($character && $character->name) {
            $description = (string) preg_replace('/\b'.preg_quote($character->name, '/').'\b/iu', $charTag, $description);
        }

        return $this->assemblePromptParts($framing, $sceneHeader, $description, $locationDirective, $characterDirective, $propCount, $styleData['modifier']);
    }

    protected function assemblePromptParts(
        string $framing,
        string $sceneHeader,
        string $description,
        ?string $locationDirective,
        ?string $characterDirective,
        int $propCount,
        string $styleModifier
    ): string {
        $parts = [
            'Cinematic film storyboard panel, aspect ratio 16:9.',
            "Camera Framing: {$framing}.",
            "Scene Setting: {$sceneHeader}.",
        ];

        if ($locationDirective) {
            $parts[] = "Location & Scenography: {$locationDirective}";
        }

        $parts[] = "Action & Composition: {$description}.";

        if ($characterDirective) {
            $parts[] = "Character Consistency: {$characterDirective}";
        }

        if ($propCount > 0) {
            $propTags = array_map(fn (int $i) => "@Atrezzo{$i}", range(1, $propCount));
            $parts[] = 'Props & Objects: Incorporate and accurately depict '.implode(', ', $propTags).' from the provided visual reference images into the shot.';
        }

        $parts[] = "Artistic Style: {$styleModifier}";

        return implode(' ', $parts);
    }

    public function resolveCharacterModel(Shot $shot, ?string $characterId = null): ?Character
    {
        if ($characterId === 'none') {
            return null;
        }

        if (! empty($characterId)) {
            return Character::find($characterId);
        }

        $project = $shot->scene?->project;
        if (! $project) {
            return null;
        }

        /** @var Collection<int, Character> $characters */
        $characters = $project->characters;
        if ($characters->isEmpty()) {
            return null;
        }

        $searchContext = $shot->description.' '.($shot->scriptBlock?->screenplay_content ?? '').' '.($shot->scriptBlock?->raw_text ?? '');
        $matched = $characters->first(fn (Character $c) => stripos($searchContext, $c->name) !== false);

        return $matched ?: $characters->first(fn (Character $c) => $c->hasReferenceImage());
    }

    public function resolveLocationModel(Shot $shot, ?string $locationId = null): ?Location
    {
        if ($locationId === 'none') {
            return null;
        }

        if (! empty($locationId)) {
            return Location::find($locationId);
        }

        $scene = $shot->scene;
        if (! $scene) {
            return null;
        }

        $location = $scene->location;
        if (! $location && $scene->project) {
            /** @var Collection<int, Location> $locations */
            $locations = $scene->project->locations;

            return $locations->first(fn (Location $loc) => stripos((string) $scene->slugline, (string) $loc->name) !== false);
        }

        return $location;
    }

    public function resolveCharacterConditioning(Shot $shot, ?string $characterId = null): ?string
    {
        $matchedChar = $this->resolveCharacterModel($shot, $characterId);
        if (! $matchedChar) {
            return null;
        }

        $directives = ["Depict character '{$matchedChar->name}'"];
        if ($matchedChar->costume_notes) {
            $directives[] = "wearing {$matchedChar->costume_notes}";
        }
        if ($matchedChar->psychology) {
            $directives[] = "expression reflects {$matchedChar->psychology}";
        }
        if ($matchedChar->hasReferenceImage()) {
            $directives[] = 'strictly respecting the character turn-around model sheet reference with precise facial features, hairstyle and physique';
        }

        return implode(', ', $directives).'.';
    }

    public function resolveLocationConditioning(Shot $shot, ?string $locationId = null): ?string
    {
        $location = $this->resolveLocationModel($shot, $locationId);
        if (! $location) {
            return null;
        }

        $details = [];
        $locName = trim((string) $location->name);
        $locType = trim((string) ($location->type ?? ''));
        if ($locName !== '') {
            $details[] = "Environment: {$locName}".($locType !== '' ? " ({$locType})" : '');
        }
        if ($location->art_department_notes) {
            $details[] = "Set design & architectural dressing: {$location->art_department_notes}";
        }
        if ($location->lighting_notes) {
            $details[] = "Lighting setup & mood: {$location->lighting_notes}";
        }
        if ($location->address_reference) {
            $details[] = "Atmosphere reference: {$location->address_reference}";
        }
        if ($location->hasReferenceImage()) {
            $details[] = 'incorporating the architectural geometry, spatial layout and visual cues from the location reference image';
        }

        return ! empty($details) ? implode('. ', $details).'.' : null;
    }
}
