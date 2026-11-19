<?php

declare(strict_types=1);

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ManageStoryboardAction;
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
        $this->storyboardModalStyle = $shot->storyboard_style ?: $this->storyboardStyle;

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
                storyboardService: $storyboardService
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
            storyboardService: $storyboardService
        );
        $this->storyboardPromptCustomized = false;
    }

    public function confirmGenerateStoryboard(StoryboardService $storyboardService, ?ManageStoryboardAction $action = null): void
    {
        $this->validateStoryboardReferences();

        $this->validate([
            'storyboardModalPrompt' => 'required|string|min:5',
        ]);

        if (! $this->storyboardShotId) {
            return;
        }

        $shot = $this->projectShot($this->storyboardShotId);
        $result = ($action ?? app(ManageStoryboardAction::class))->generate(
            shot: $shot,
            style: $this->storyboardModalStyle,
            prompt: $this->storyboardModalPrompt,
            characterId: $this->storyboardCharacterId,
            locationId: $this->storyboardLocationId,
            propImages: $this->storyboardPropUploads,
            user: auth()->user() ?? $this->project->user,
            storyboardService: $storyboardService
        );

        if ($result['success']) {
            $this->project->update(['storyboard_style' => $this->storyboardModalStyle]);
            $this->closeStoryboardModal();
        }

        session()->flash($result['flash_type'], $result['message']);
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
