<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ManageStoryboardAction;
use App\Services\StoryboardService;

trait WithEditorStoryboard
{
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

    public function updatedStoryboardModalPrompt(): void
    {
        $this->storyboardPromptCustomized = true;
    }

    public function updatedStoryboardModalStyle(StoryboardService $storyboardService): void
    {
        if (! $this->storyboardPromptCustomized && $this->storyboardShotId) {
            $this->resetStoryboardModalPrompt($storyboardService);
        }
    }

    public function updatedStoryboardCharacterId(StoryboardService $storyboardService): void
    {
        if (! $this->storyboardPromptCustomized && $this->storyboardShotId) {
            $this->resetStoryboardModalPrompt($storyboardService);
        }
    }

    public function updatedStoryboardLocationId(StoryboardService $storyboardService): void
    {
        if (! $this->storyboardPromptCustomized && $this->storyboardShotId) {
            $this->resetStoryboardModalPrompt($storyboardService);
        }
    }

    public function updatedStoryboardPropUploads(StoryboardService $storyboardService): void
    {
        $this->validate([
            'storyboardPropUploads.*' => 'image|max:8192',
        ]);

        if (count($this->storyboardPropUploads) > 3) {
            $this->storyboardPropUploads = array_slice($this->storyboardPropUploads, 0, 3);
            session()->flash('warning', 'Se ha limitado el atrezzo a un máximo de 3 imágenes.');
        }

        if (! $this->storyboardPromptCustomized && $this->storyboardShotId) {
            $this->resetStoryboardModalPrompt($storyboardService);
        }
    }

    public function removeStoryboardProp(int $index, StoryboardService $storyboardService): void
    {
        if (isset($this->storyboardPropUploads[$index])) {
            unset($this->storyboardPropUploads[$index]);
            $this->storyboardPropUploads = array_values($this->storyboardPropUploads);
        }

        if (! $this->storyboardPromptCustomized && $this->storyboardShotId) {
            $this->resetStoryboardModalPrompt($storyboardService);
        }
    }

    public function confirmGenerateStoryboard(StoryboardService $storyboardService, ?ManageStoryboardAction $action = null): void
    {
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

    public function openStoryboardPreview(string $shotId): void
    {
        $shot = $this->projectShot($shotId);
        $this->previewStoryboardUrl = $shot->storyboard_url;
        $this->previewStoryboardTitle = "Shot #{$shot->shot_number} ({$shot->shot_type})";
    }

    public function closeStoryboardPreview(): void
    {
        $this->previewStoryboardUrl = null;
        $this->previewStoryboardTitle = null;
    }
}
