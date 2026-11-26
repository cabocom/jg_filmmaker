<?php

declare(strict_types=1);

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ManageStoryboardAction;
use App\DTOs\Storyboard\VisualAestheticConfigDTO;
use App\Enums\ProjectCategory;
use App\Services\StoryboardService;
use Illuminate\Validation\Rule;

trait WithEditorStoryboard
{
    use WithEditorStoryboardModalEvents;

    public function setStoryboardStyle(string $style, ?ManageStoryboardAction $action = null): void
    {
        if (! ($action ?? app(ManageStoryboardAction::class))->updateProjectStyle($this->project, $style)) {
            return;
        }

        $this->storyboardStyle = $style;
        $styleName = StoryboardService::STYLES[$style]['name'];
        session()->flash('success', "Estilo de Storyboard actualizado a: {$styleName}.");
    }

    public function generateShotStoryboard(string $shotId, StoryboardService $storyboardService, ?ManageStoryboardAction $action = null): void
    {
        $shot = $this->projectShot($shotId);
        $result = ($action ?? app(ManageStoryboardAction::class))->generate(
            shot: $shot,
            style: $this->storyboardStyle,
            prompt: $shot->storyboard_prompt ?: '',
            characterId: null,
            locationId: null,
            propImages: [],
            user: auth()->user() ?? $this->project->user,
            storyboardService: $storyboardService
        );

        session()->flash($result['flash_type'], $result['message']);
    }

    public function openStoryboardModal(string $shotId, StoryboardService $storyboardService, ?ManageStoryboardAction $action = null): void
    {
        $shot = $this->projectShot($shotId);
        $this->storyboardShotId = $shotId;

        $isAiVideo = $this->project->categoryEnum() === ProjectCategory::AI_VIDEO;
        $defaultRenderMode = $isAiVideo
            ? VisualAestheticConfigDTO::MODE_FULL_BLEED
            : VisualAestheticConfigDTO::MODE_STORYBOARD;

        $rawConfig = is_array($shot->visual_aesthetic_config)
            ? $shot->visual_aesthetic_config
            : (is_array($this->project->visual_aesthetic_config) ? $this->project->visual_aesthetic_config : []);

        $aestheticConfig = VisualAestheticConfigDTO::fromArray($rawConfig, $defaultRenderMode);
        $this->storyboardRenderMode = $aestheticConfig->renderMode;
        $this->storyboardColorPalette = $aestheticConfig->colorPalette ?? '';
        $this->storyboardLightingStyle = $aestheticConfig->lightingStyle ?? '';
        $this->storyboardCameraLens = $aestheticConfig->cameraLens ?? '';
        $this->storyboardNegativeDirectives = $aestheticConfig->negativeDirectives ?? '';

        $resolvedStyle = (string) ($shot->storyboard_style ?: $this->storyboardStyle);
        if ($isAiVideo && in_array($resolvedStyle, ['', 'storyboard'], true)) {
            $resolvedStyle = 'editorial_documentary_35mm';
        }
        $this->storyboardModalStyle = $resolvedStyle;

        $detectedChar = $storyboardService->resolveCharacterModel($shot);
        $this->storyboardCharacterId = $detectedChar?->id;

        $detectedLoc = $storyboardService->resolveLocationModel($shot);
        $this->storyboardLocationId = $detectedLoc?->id;

        $this->storyboardPropUploads = [];
        $this->storyboardPromptCustomized = false;

        $this->storyboardModalPrompt = $shot->storyboard_prompt
            ?: ($action ?? app(ManageStoryboardAction::class))->buildPrompt(
                shot: $shot,
                style: $this->storyboardModalStyle,
                characterId: $this->storyboardCharacterId,
                locationId: $this->storyboardLocationId,
                propCount: 0,
                storyboardService: $storyboardService,
                aestheticConfig: $aestheticConfig
            );

        $this->showStoryboardModal = true;
    }

    public function closeStoryboardModal(): void
    {
        $this->showStoryboardModal = false;
        $this->storyboardShotId = null;
        $this->storyboardPropUploads = [];
        $this->storyboardPromptCustomized = false;
        $this->storyboardModalPrompt = '';
    }

    public function resetStoryboardModalPrompt(StoryboardService $storyboardService, ?ManageStoryboardAction $action = null): void
    {
        if (! $this->storyboardShotId) {
            return;
        }

        $this->validateStoryboardReferences();

        $shot = $this->projectShot($this->storyboardShotId);
        $this->storyboardModalPrompt = ($action ?? app(ManageStoryboardAction::class))->buildPrompt(
            shot: $shot,
            style: $this->storyboardModalStyle,
            characterId: $this->storyboardCharacterId,
            locationId: $this->storyboardLocationId,
            propCount: count($this->storyboardPropUploads),
            storyboardService: $storyboardService,
            aestheticConfig: $this->buildModalAestheticConfig()
        );
        $this->storyboardPromptCustomized = false;
    }

    public function confirmGenerateStoryboard(StoryboardService $storyboardService, ?ManageStoryboardAction $action = null): void
    {
        $result = $this->executeModalStoryboardGeneration($storyboardService, $action);

        if ($result === null) {
            return;
        }

        if ($result['success']) {
            $this->closeStoryboardModal();
        }

        session()->flash($result['flash_type'], $result['message']);
    }

    public function testGenerateStoryboardInModal(StoryboardService $storyboardService, ?ManageStoryboardAction $action = null): void
    {
        $result = $this->executeModalStoryboardGeneration($storyboardService, $action);

        if ($result === null) {
            return;
        }

        if ($result['success']) {
            $this->storyboardPreviewVersion++;
        }

        $this->showStoryboardModal = true;
        session()->flash($result['flash_type'], $result['message']);
    }

    /**
     * @return array{success: bool, flash_type: string, message: string}|null
     */
    protected function executeModalStoryboardGeneration(StoryboardService $storyboardService, ?ManageStoryboardAction $action = null): ?array
    {
        $this->validateStoryboardReferences();

        $this->validate([
            'storyboardModalPrompt' => 'required|string|min:5',
        ]);

        if (! $this->storyboardShotId) {
            return null;
        }

        $shot = $this->projectShot($this->storyboardShotId);
        $aestheticConfig = $this->buildModalAestheticConfig();

        $result = ($action ?? app(ManageStoryboardAction::class))->generate(
            shot: $shot,
            style: $this->storyboardModalStyle,
            prompt: $this->storyboardModalPrompt,
            characterId: $this->storyboardCharacterId,
            locationId: $this->storyboardLocationId,
            propImages: $this->storyboardPropUploads,
            user: auth()->user() ?? $this->project->user,
            storyboardService: $storyboardService,
            aestheticConfig: $aestheticConfig
        );

        if ($result['success']) {
            $this->project->update([
                'storyboard_style' => $this->storyboardModalStyle,
                'visual_aesthetic_config' => $aestheticConfig->toArray(),
            ]);
        }

        return $result;
    }

    protected function buildModalAestheticConfig(): VisualAestheticConfigDTO
    {
        $defaultRenderMode = $this->project->categoryEnum() === ProjectCategory::AI_VIDEO
            ? VisualAestheticConfigDTO::MODE_FULL_BLEED
            : VisualAestheticConfigDTO::MODE_STORYBOARD;

        return VisualAestheticConfigDTO::fromArray([
            'render_mode' => $this->storyboardRenderMode,
            'color_palette' => $this->storyboardColorPalette,
            'lighting_style' => $this->storyboardLightingStyle,
            'camera_lens' => $this->storyboardCameraLens,
            'negative_directives' => $this->storyboardNegativeDirectives,
        ], $defaultRenderMode);
    }

    protected function validateStoryboardReferences(): void
    {
        if ($this->storyboardCharacterId === '') {
            $this->storyboardCharacterId = null;
        }

        if ($this->storyboardLocationId === '') {
            $this->storyboardLocationId = null;
        }

        $this->validate([
            'storyboardCharacterId' => [
                'nullable',
                Rule::exists('characters', 'id')->where('project_id', $this->project->id),
            ],
            'storyboardLocationId' => [
                'nullable',
                Rule::exists('locations', 'id')->where('project_id', $this->project->id),
            ],
        ]);
    }
}
