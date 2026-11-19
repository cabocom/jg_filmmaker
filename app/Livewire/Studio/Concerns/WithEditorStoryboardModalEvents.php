<?php

declare(strict_types=1);

namespace App\Livewire\Studio\Concerns;

use App\Services\StoryboardService;

trait WithEditorStoryboardModalEvents
{
    public function updatedStoryboardModalPrompt(): void
    {
        $this->storyboardPromptCustomized = true;
    }

    public function updatedStoryboardModalStyle(?string $value = null, ?StoryboardService $storyboardService = null): void
    {
        $storyboardService ??= app(StoryboardService::class);

        if (! $this->storyboardPromptCustomized && $this->storyboardShotId) {
            $this->resetStoryboardModalPrompt($storyboardService);
        }
    }

    public function updatedStoryboardCharacterId(?string $value = null, ?StoryboardService $storyboardService = null): void
    {
        $storyboardService ??= app(StoryboardService::class);
        $this->validateStoryboardReferences();

        if (! $this->storyboardPromptCustomized && $this->storyboardShotId) {
            $this->resetStoryboardModalPrompt($storyboardService);
        }
    }

    public function updatedStoryboardLocationId(?string $value = null, ?StoryboardService $storyboardService = null): void
    {
        $storyboardService ??= app(StoryboardService::class);
        $this->validateStoryboardReferences();

        if (! $this->storyboardPromptCustomized && $this->storyboardShotId) {
            $this->resetStoryboardModalPrompt($storyboardService);
        }
    }

    public function updatedStoryboardPropUploads(mixed $value = null, ?StoryboardService $storyboardService = null): void
    {
        $storyboardService ??= app(StoryboardService::class);
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
