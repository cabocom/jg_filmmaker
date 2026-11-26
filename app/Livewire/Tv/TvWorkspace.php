<?php

declare(strict_types=1);

namespace App\Livewire\Tv;

use App\Actions\Tv\ManageTvRundownAction;
use App\Actions\Tv\SyncTvChannelConfigAction;
use App\Models\TvAdSlot;
use App\Models\TvDaySchedule;
use App\Models\TvRundown;
use App\Models\TvVideo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class TvWorkspace extends Component
{
    private const array SECTIONS = ['rundowns', 'videos', 'schedule', 'ads', 'config'];

    #[Url]
    public string $activeSection = 'rundowns';

    #[Url]
    public bool $embed = false;

    public string $newTitle = '';

    public string $newType = 'pill';

    public string $newSourceKind = 'wikipedia';

    public string $newTopicOrUrl = '';

    public string $newScriptText = '';

    public string $newVisualCredit = '';

    public string $newTtsVoice = 'Kore';

    public bool $newPublishToPodcast = false;

    public bool $newGenerateShort = false;

    public string $broadcastProfile = '720p30';

    public string $videosDir = 'ai-today/videos';

    public string $adsDir = 'ai-today/ads';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(SyncTvChannelConfigAction $configAction): void
    {
        $config = $configAction->getOrCreateConfig();
        $this->broadcastProfile = (string) ($config->resolution_profile ?: '720p30');
        $this->newTtsVoice = (string) ($config->tts_voice ?: 'Kore');
        $this->videosDir = (string) data_get($config->metadata, 'videos_dir', 'ai-today/videos');
        $this->adsDir = (string) data_get($config->metadata, 'ads_dir', 'ai-today/ads');
    }

    public function switchSection(string $section): void
    {
        $this->activeSection = in_array($section, self::SECTIONS, true) ? $section : 'rundowns';
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    public function createRundown(ManageTvRundownAction $action): void
    {
        $this->reset(['statusMessage', 'errorMessage']);

        $rules = [
            'newTitle' => ['nullable', 'string', 'max:150'],
            'newType' => ['required', 'string', 'in:pill,program,short'],
            'newSourceKind' => ['required', 'string', 'in:wikipedia,manual'],
            'newTopicOrUrl' => $this->newSourceKind === 'wikipedia'
                ? ['required', 'string', 'max:500']
                : ['nullable', 'string', 'max:500'],
            'newScriptText' => $this->newSourceKind === 'manual' && trim($this->newTitle) === ''
                ? ['required', 'string', 'max:20000']
                : ['nullable', 'string', 'max:20000'],
            'newVisualCredit' => ['nullable', 'string', 'max:255'],
            'newTtsVoice' => ['required', 'string', 'max:100'],
        ];

        $this->validate($rules);

        $rundown = $action->createFromPromptOrSource([
            'title' => $this->newTitle,
            'format' => $this->newType,
            'source_type' => $this->newSourceKind,
            'topic_or_url' => $this->newTopicOrUrl !== '' ? $this->newTopicOrUrl : null,
            'script_text' => $this->newScriptText !== '' ? $this->newScriptText : null,
            'visual_credit' => $this->newVisualCredit !== '' ? $this->newVisualCredit : null,
            'tts_voice' => $this->newTtsVoice,
            'resolution_profile' => $this->broadcastProfile,
        ], Auth::user());

        $this->reset(['newTitle', 'newTopicOrUrl', 'newScriptText', 'newVisualCredit']);
        $this->statusMessage = "Escaleta «{$rundown->title}» creada y vinculada al Estudio.";
    }

    public function dispatchProduction(string $rundownId, ManageTvRundownAction $action): void
    {
        $this->reset(['statusMessage', 'errorMessage']);
        $rundown = TvRundown::query()->findOrFail($rundownId);

        try {
            $action->dispatchBroadcastProduction($rundown);
            $this->statusMessage = "Producción broadcast encolada para «{$rundown->title}».";
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteRundown(string $rundownId, ManageTvRundownAction $action): void
    {
        $this->reset(['statusMessage', 'errorMessage']);
        $rundown = TvRundown::query()->findOrFail($rundownId);
        $title = $rundown->title;
        $action->delete($rundown);
        $this->statusMessage = "Escaleta «{$title}» eliminada.";
    }

    public function saveChannelConfig(SyncTvChannelConfigAction $action): void
    {
        $this->reset(['statusMessage', 'errorMessage']);

        $this->validate([
            'broadcastProfile' => ['required', 'string', 'in:720p30,1080p30'],
            'newTtsVoice' => ['required', 'string', 'max:100'],
            'videosDir' => ['required', 'string', 'max:255'],
            'adsDir' => ['required', 'string', 'max:255'],
        ]);

        $config = $action->getOrCreateConfig();
        $metadata = is_array($config->metadata) ? $config->metadata : [];
        $metadata['videos_dir'] = trim($this->videosDir);
        $metadata['ads_dir'] = trim($this->adsDir);
        $metadata['publish_to_podcast'] = $this->newPublishToPodcast;
        $metadata['generate_short'] = $this->newGenerateShort;

        $action->saveConfig([
            'resolution_profile' => $this->broadcastProfile,
            'tts_voice' => $this->newTtsVoice,
            'metadata' => $metadata,
        ], $config);

        $this->statusMessage = 'Configuración del canal guardada correctamente.';
    }

    public function render(SyncTvChannelConfigAction $configAction): View
    {
        $channelConfig = $configAction->getOrCreateConfig();

        return view('livewire.tv.tv-workspace', [
            'channelConfig' => $channelConfig,
            'rundowns' => TvRundown::query()->with(['project', 'tvVideo', 'broadcastJob'])->latest()->get(),
            'videos' => TvVideo::query()->with('rundown')->latest()->get(),
            'schedules' => TvDaySchedule::query()->orderByDesc('air_date')->get(),
            'adSlots' => TvAdSlot::query()->orderBy('sort_order')->get(),
        ])->layoutData([
            'title' => 'Consola de Televisión IA',
            'embed' => $this->embed,
        ]);
    }
}
