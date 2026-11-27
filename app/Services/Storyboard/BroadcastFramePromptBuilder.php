<?php

declare(strict_types=1);

namespace App\Services\Storyboard;

use App\DTOs\Storyboard\VisualAestheticConfigDTO;
use App\Models\BroadcastJob;
use App\Models\Shot;
use App\Models\TvRundown;
use App\Models\TvVisualStyle;

final readonly class BroadcastFramePromptBuilder
{
    /**
     * @var array<string, string>
     */
    private const array FRAMING_MAP = [
        'PG' => 'Wide establishing shot',
        'PM' => 'Medium shot',
        'PP' => 'Close-up shot',
        'PD' => 'Detail insert shot',
        'PA' => 'Medium-wide shot',
    ];

    /**
     * @param  array<string, array{name: string, badge: string, description: string, modifier: string}>  $styles
     */
    public function __construct(
        private StoryboardPromptBuilder $baseBuilder,
        private array $styles,
    ) {}

    public function buildPrompt(
        Shot $shot,
        string $styleKey,
        VisualAestheticConfigDTO $config,
        ?string $characterId = null,
        ?string $locationId = null,
        int $propCount = 0,
    ): string {
        $shot->loadMissing(['scene.project.characters', 'scene.project.locations', 'scene.project.visualStyle', 'scene.location', 'scriptBlock']);

        $framing = $this->resolveFraming($shot->shot_type);
        $effectiveCharacterId = $this->resolveEffectiveCharacterId($shot, $characterId);
        $description = $this->resolveDescription($shot, $effectiveCharacterId);
        $styleDirective = $this->resolveStyleDirective($shot, $styleKey);

        $parts = [];
        if ($styleDirective !== null) {
            $parts[] = "MANDATORY VISUAL STYLE: {$styleDirective}.";
        }
        $parts[] = "Full-bleed 16:9 widescreen broadcast frame. {$framing} showing {$description}";

        $this->appendBibleContext($parts, $shot, $effectiveCharacterId, $locationId, $propCount);
        $this->appendAestheticDirectives($parts, $config);

        $parts[] = 'Edge-to-edge full-bleed composition filling the entire 16:9 frame with no borders, no letterbox bars, no panel outlines, no text overlays, no watermarks, and no storyboard labels.';

        return implode(' ', $parts);
    }

    private function resolveFraming(?string $shotType): string
    {
        $normalized = strtoupper(trim((string) $shotType));

        if ($normalized !== '' && isset(self::FRAMING_MAP[$normalized])) {
            return self::FRAMING_MAP[$normalized];
        }

        $fallback = trim((string) $shotType);

        return $fallback !== '' ? $fallback : 'Medium shot';
    }

    private function resolveDescription(Shot $shot, ?string $characterId): string
    {
        $description = $this->stripSpeakerPrefix((string) $shot->description);
        if ($description === '') {
            $description = 'a compelling visual scene in motion.';
        }
        $character = $this->baseBuilder->resolveCharacterModel($shot, $characterId);

        if ($character && $character->name) {
            $charTag = '@'.preg_replace('/\s+/', '_', trim($character->name));
            $description = (string) preg_replace('/\b'.preg_quote($character->name, '/').'\b/iu', $charTag, $description);
        }

        return rtrim($description, '.').'.';
    }

    private function stripSpeakerPrefix(string $description): string
    {
        $cleaned = preg_replace(
            '/^\s*(?:HOST|LOCUTOR|PRESENTADOR|NARRADOR)(?:\s*\((?:V\.O\.|VO|OFF|CONT\.?)\))?\s*:\s*/imu',
            '',
            $description
        );
        $normalized = preg_replace('/\s+/', ' ', (string) $cleaned);

        return trim((string) $normalized);
    }

    private function resolveEffectiveCharacterId(Shot $shot, ?string $characterId): ?string
    {
        if ($characterId !== null || ! $this->isPillFormat($shot)) {
            return $characterId;
        }

        $character = $this->baseBuilder->resolveCharacterModel($shot, null);
        if ($character === null) {
            return 'none';
        }

        $name = trim((string) $character->name);
        if (in_array(strtolower($name), ['presentador', 'host', 'locutor'], true)) {
            return 'none';
        }

        if ($name === '' || stripos((string) $shot->description, $name) === false) {
            return 'none';
        }

        return null;
    }

    private function isPillFormat(Shot $shot): bool
    {
        $projectId = $shot->scene?->project_id;
        if ($projectId === null) {
            return false;
        }

        $rundownFormat = TvRundown::query()
            ->where('project_id', $projectId)
            ->value('format');

        if (in_array(strtolower(trim((string) $rundownFormat)), ['pill', 'pildora'], true)) {
            return true;
        }

        $jobFormat = BroadcastJob::query()
            ->where('project_id', $projectId)
            ->value('format');

        return in_array(strtolower(trim((string) $jobFormat)), ['pill', 'pildora'], true);
    }

    /**
     * @param  list<string>  $parts
     */
    private function appendBibleContext(
        array &$parts,
        Shot $shot,
        ?string $characterId,
        ?string $locationId,
        int $propCount,
    ): void {
        $locationDirective = $this->baseBuilder->resolveLocationConditioning($shot, $locationId);
        if ($locationDirective !== null) {
            $parts[] = "Location & Scenography: {$locationDirective}";
        }

        $characterDirective = $this->baseBuilder->resolveCharacterConditioning($shot, $characterId);
        if ($characterDirective !== null) {
            $parts[] = "Character Consistency: {$characterDirective}";
        }

        if ($propCount > 0) {
            $propTags = array_map(fn (int $i): string => "@Atrezzo{$i}", range(1, $propCount));
            $parts[] = 'Incorporate '.implode(', ', $propTags).' from the provided reference images naturally into the scene.';
        }
    }

    /**
     * @param  list<string>  $parts
     */
    private function appendAestheticDirectives(array &$parts, VisualAestheticConfigDTO $config): void
    {
        if ($config->colorPalette !== null) {
            $parts[] = "Color palette: {$config->colorPalette}.";
        }

        if ($config->lightingStyle !== null) {
            $parts[] = "Lighting: {$config->lightingStyle}.";
        }

        if ($config->cameraLens !== null) {
            $parts[] = "Optical look: {$config->cameraLens}.";
        }

        if ($config->negativeDirectives !== null) {
            $parts[] = "Avoid: {$config->negativeDirectives}.";
        }
    }

    private function resolveStyleDirective(Shot $shot, string $styleKey): ?string
    {
        $project = $shot->scene?->project;
        $visualStyle = $project?->visualStyle;

        if (! $visualStyle instanceof TvVisualStyle && $project !== null) {
            $visualStyle = TvRundown::query()
                ->where('project_id', $project->id)
                ->with('visualStyle')
                ->first()
                ?->visualStyle;
        }

        if ($visualStyle instanceof TvVisualStyle) {
            $promptBase = trim((string) $visualStyle->prompt_base);
            if ($promptBase !== '') {
                $characteristics = trim((string) ($visualStyle->characteristics ?? ''));

                return $characteristics !== '' ? "{$promptBase} {$characteristics}" : $promptBase;
            }
        }

        $rawConfig = is_array($shot->visual_aesthetic_config) && $shot->visual_aesthetic_config !== []
            ? $shot->visual_aesthetic_config
            : (is_array($project?->visual_aesthetic_config) ? $project->visual_aesthetic_config : []);

        $configPrompt = is_string($rawConfig['style_prompt'] ?? null) ? trim($rawConfig['style_prompt']) : '';
        if ($configPrompt !== '') {
            $configCharacteristics = is_string($rawConfig['style_characteristics'] ?? null)
                ? trim($rawConfig['style_characteristics'])
                : '';

            return $configCharacteristics !== '' ? "{$configPrompt} {$configCharacteristics}" : $configPrompt;
        }

        $styleModifier = $this->styles[$styleKey]['modifier']
            ?? $this->styles['grayscale_sketch']['modifier']
            ?? null;

        return is_string($styleModifier) && trim($styleModifier) !== '' ? trim($styleModifier) : null;
    }
}
