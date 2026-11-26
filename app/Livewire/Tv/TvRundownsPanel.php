<?php

declare(strict_types=1);

namespace App\Livewire\Tv;

use App\Actions\Tv\ManageTvRundownAction;
use App\Actions\Tv\ScanAndSyncTvDriveMediaAction;
use App\Models\TvRundown;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;
use Throwable;

class TvRundownsPanel extends Component
{
    public string $sourceKind = 'wikipedia';

    public string $topicOrUrl = '';

    public string $title = '';

    public string $language = 'es';

    public string $rundownType = 'pill';

    public string $scriptText = '';

    public bool $publishToPodcast = false;

    public bool $generateShort = false;

    public bool $evergreenFallback = false;

    public string $ttsProvider = 'gemini';

    public string $ttsVoice = 'Kore';

    public ?string $editingRundownId = null;

    public string $editTitle = '';

    public string $editType = 'pill';

    public string $editScriptText = '';

    public bool $editPublishToPodcast = false;

    public bool $editGenerateShort = false;

    public bool $editEvergreenFallback = false;

    public string $editTtsProvider = 'gemini';

    public string $editTtsVoice = 'Kore';

    protected bool $justInitiated = false;

    public function boot(): void
    {
        if (! (bool) Auth::user()?->is_admin) {
            abort(403);
        }
    }

    public function mount(): void
    {
        if (! (bool) Auth::user()?->is_admin) {
            abort(403);
        }
    }

    public function createRundown(ManageTvRundownAction $action): void
    {
        $this->validateRundownInput();
        try {
            $action->createFromPromptOrSource([
                'channel_slug' => 'ai-today',
                'type' => $this->rundownType,
                'source_kind' => $this->sourceKind,
                'title' => $this->title,
                'topic_or_url' => $this->topicOrUrl,
                'language' => $this->language,
                'script_text' => $this->scriptText,
                'publish_to_podcast' => $this->publishToPodcast,
                'generate_short' => $this->generateShort,
                'evergreen_fallback' => $this->evergreenFallback,
                'tts_provider' => $this->ttsProvider,
                'tts_voice' => $this->ttsVoice,
            ], Auth::user());
            $this->reset(['title', 'topicOrUrl', 'scriptText']);
            session()->flash('success', 'Escaleta creada exitosamente y vinculada a un Proyecto Videos IA en el Estudio.');
        } catch (InvalidArgumentException $e) {
            $this->addError('topicOrUrl', $e->getMessage());
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function startEditing(string $rundownId): void
    {
        $rundown = TvRundown::query()->where('channel_slug', 'ai-today')->findOrFail($rundownId);
        if ($rundown->isBusy()) {
            session()->flash('error', 'La escaleta está en proceso y no puede editarse.');

            return;
        }
        $this->editingRundownId = $rundown->id;
        $this->editTitle = (string) $rundown->title;
        $this->editType = (string) ($rundown->type ?: $rundown->format ?: 'pill');
        $this->editScriptText = (string) ($rundown->script_text ?? '');
        $this->editPublishToPodcast = (bool) $rundown->publish_to_podcast;
        $this->editGenerateShort = (bool) $rundown->generate_short;
        $this->editEvergreenFallback = (bool) $rundown->evergreen_fallback;
        $this->editTtsProvider = (string) ($rundown->tts_provider ?: 'gemini');
        $this->editTtsVoice = (string) ($rundown->tts_voice ?: 'Kore');
    }

    public function saveDraftEdits(ManageTvRundownAction $action): void
    {
        if (! $this->editingRundownId) {
            return;
        }
        $rundown = TvRundown::query()->where('channel_slug', 'ai-today')->findOrFail($this->editingRundownId);
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editType' => 'required|in:pill,news,debate,music',
            'editScriptText' => 'nullable|string',
            'editTtsProvider' => 'required|in:gemini,kokoro,elevenlabs,edge',
            'editTtsVoice' => 'required|string|max:64',
        ]);
        try {
            $action->updateDraft($rundown, [
                'title' => $this->editTitle,
                'type' => $this->editType,
                'script_text' => $this->editScriptText,
                'publish_to_podcast' => $this->editPublishToPodcast,
                'generate_short' => $this->editGenerateShort,
                'evergreen_fallback' => $this->editEvergreenFallback,
                'tts_provider' => $this->editTtsProvider,
                'tts_voice' => $this->editTtsVoice,
            ]);
            $this->editingRundownId = null;
            session()->flash('success', 'Escaleta actualizada correctamente.');
        } catch (InvalidArgumentException|RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function triggerVideoRender(string $rundownId, ManageTvRundownAction $action): void
    {
        $rundown = TvRundown::query()->where('channel_slug', 'ai-today')->findOrFail($rundownId);
        try {
            $updated = $action->dispatchBroadcastProduction($rundown);
            $this->justInitiated = true;
            session()->flash('success', 'Render despachado: '.($updated->broadcast_job_id ?? ''));
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function deleteRundown(string $rundownId, ManageTvRundownAction $action): void
    {
        $rundown = TvRundown::query()->where('channel_slug', 'ai-today')->findOrFail($rundownId);
        try {
            $action->delete($rundown);
            session()->flash('success', 'Escaleta eliminada.');
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function retryRundown(string $rundownId, ManageTvRundownAction $action): void
    {
        $rundown = TvRundown::query()->where('channel_slug', 'ai-today')->findOrFail($rundownId);
        try {
            $action->retry($rundown);
            $this->justInitiated = true;
            session()->flash('success', 'Escaleta reintentada correctamente.');
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(ScanAndSyncTvDriveMediaAction $syncAction): View
    {
        $rundowns = TvRundown::query()->with('project')->where('channel_slug', 'ai-today')->latest()->get();
        $hasBusyRundowns = $rundowns->contains(fn (TvRundown $r): bool => $r->isBusy());

        if (! $this->justInitiated && $rundowns->contains(fn (TvRundown $r): bool => in_array((string) $r->stage, ['queued', 'rendering'], true))) {
            $syncAction->refreshActiveJobsOnly('ai-today');
            $rundowns = TvRundown::query()->with('project')->where('channel_slug', 'ai-today')->latest()->get();
            $hasBusyRundowns = $rundowns->contains(fn (TvRundown $r): bool => $r->isBusy());
        }
        $this->justInitiated = false;

        return view('livewire.tv.rundowns-panel', [
            'rundowns' => $rundowns,
            'hasBusyRundowns' => $hasBusyRundowns,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRundownInput(): array
    {
        return $this->validate([
            'sourceKind' => 'required|in:wikipedia,manual',
            'topicOrUrl' => $this->sourceKind === 'wikipedia' ? 'required|string|max:500' : 'nullable|string|max:500',
            'title' => $this->sourceKind === 'manual' ? 'required|string|max:255' : 'nullable|string|max:255',
            'language' => 'required|in:es,en',
            'rundownType' => 'required|in:pill,news,debate,music',
            'scriptText' => $this->sourceKind === 'manual' ? 'required|string' : 'nullable|string',
            'ttsProvider' => 'required|in:gemini,kokoro,elevenlabs,edge',
            'ttsVoice' => 'required|string|max:64',
        ]);
    }
}
