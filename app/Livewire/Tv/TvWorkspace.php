<?php

declare(strict_types=1);

namespace App\Livewire\Tv;

use App\Actions\Tv\ManageTvVideoAction;
use App\Actions\Tv\ScheduleAiTodayTvDayAction;
use App\Actions\Tv\SyncTvChannelConfigAction;
use App\Models\TvAdSlot;
use App\Models\TvVideo;
use App\Services\Broadcast\TvMediaPathResolver;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
class TvWorkspace extends Component
{
    #[Url(as: 'tv_section')]
    public string $activeSection = 'schedule';

    #[Url]
    public bool $embed = false;

    public string $selectedDate = '';

    /** @var array<int, array<string, mixed>> */
    public array $scheduleBlocks = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public string $videoTypeFilter = 'all';

    public string $youtubeChannelId = '';

    public string $youtubeStreamKey = '';

    public string $youtubeOauthJson = '';

    public string $voiceNarrator = 'Kore';

    public string $voiceOrus = 'Orus';

    public string $voicePuck = 'Puck';

    public string $voiceAoede = 'Aoede';

    public string $broadcastProfile = '720p30';

    public string $videosDir = 'ai-today/videos';

    public string $adsDir = 'ai-today/ads';

    private function assertAdmin(): void
    {
        if (! (bool) Auth::user()?->is_admin) {
            abort(403, 'Acceso restringido a administradores autenticados.');
        }
    }

    public function mount(SyncTvChannelConfigAction $configAction): void
    {
        $this->assertAdmin();

        $validSections = ['schedule', 'rundowns', 'videos', 'ads', 'config'];
        if ($this->activeSection === 'schedule' && request()->query('tv_section') === null && in_array(session('tv_active_section'), $validSections, true)) {
            $this->activeSection = (string) session('tv_active_section');
        }

        if (! in_array($this->activeSection, $validSections, true)) {
            $this->activeSection = 'schedule';
        }

        $this->selectedDate = now()->toDateString();
        $config = $configAction->getOrCreateConfig('ai-today');
        $this->youtubeChannelId = (string) ($config->youtube_channel_id ?? '');
        $voices = is_array($config->voice_profiles_json) ? $config->voice_profiles_json : [];
        $this->voiceNarrator = (string) ($voices['narrator'] ?? 'Kore');
        $this->voiceOrus = (string) ($voices['orus'] ?? 'Orus');
        $this->voicePuck = (string) ($voices['puck'] ?? 'Puck');
        $this->voiceAoede = (string) ($voices['aoede'] ?? 'Aoede');
        $this->broadcastProfile = (string) ($config->broadcast_profile ?: '720p30');
        $this->videosDir = (string) ($config->videos_dir ?: 'ai-today/videos');
        $this->adsDir = (string) ($config->ads_dir ?: 'ai-today/ads');
    }

    public function switchSection(string $section): void
    {
        $this->assertAdmin();
        if (in_array($section, ['schedule', 'rundowns', 'videos', 'ads', 'config'], true)) {
            $this->activeSection = $section;
            session(['tv_active_section' => $section]);
        }
    }

    public function saveManualSchedule(ScheduleAiTodayTvDayAction $action): void
    {
        $this->assertAdmin();
        $this->errorMessage = null;
        try {
            $this->validate(['selectedDate' => ['required', 'date_format:Y-m-d']]);
            $date = $this->selectedDate;
            $existing = $action->getOrPlanDay($date);
            $blocks = ! empty($this->scheduleBlocks) ? $this->scheduleBlocks : (is_array($existing->blocks_json) ? $existing->blocks_json : []);
            $action->saveManualBlocks($date, $blocks);
            $this->statusMessage = 'Parrilla diaria guardada y bloqueada manualmente.';
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function forceReplanWithAi(ScheduleAiTodayTvDayAction $action): void
    {
        $this->assertAdmin();
        $this->errorMessage = null;
        try {
            $this->validate(['selectedDate' => ['required', 'date_format:Y-m-d']]);
            $action->proposeAiDay($this->selectedDate, forceReplan: true);
            $this->statusMessage = 'Parrilla replanificada con IA exitosamente.';
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function saveChannelConfig(SyncTvChannelConfigAction $action, TvMediaPathResolver $pathResolver): void
    {
        $this->assertAdmin();
        $this->validate([
            'broadcastProfile' => ['required', 'in:720p30,1080p30'],
            'videosDir' => ['required', 'string', 'max:255'],
            'adsDir' => ['required', 'string', 'max:255'],
        ]);

        $hasErrors = false;
        try {
            $pathResolver->normalizeRelativePath($this->videosDir, requireMp4Extension: false);
        } catch (InvalidArgumentException $e) {
            $this->addError('videosDir', $e->getMessage());
            $hasErrors = true;
        }
        try {
            $pathResolver->normalizeRelativePath($this->adsDir, requireMp4Extension: false);
        } catch (InvalidArgumentException $e) {
            $this->addError('adsDir', $e->getMessage());
            $hasErrors = true;
        }
        if ($hasErrors) {
            return;
        }

        try {
            $action->saveMediaStorageConfig('ai-today', $this->broadcastProfile, $this->videosDir, $this->adsDir);
        } catch (InvalidArgumentException $e) {
            $this->addError('broadcastProfile', $e->getMessage());

            return;
        }

        $action->saveYoutubeCredentials('ai-today', $this->youtubeChannelId, $this->youtubeOauthJson, $this->youtubeStreamKey);
        $action->saveVoiceProfiles('ai-today', [
            'narrator' => $this->voiceNarrator,
            'orus' => $this->voiceOrus,
            'puck' => $this->voicePuck,
            'aoede' => $this->voiceAoede,
        ]);

        $this->youtubeStreamKey = '';
        $this->youtubeOauthJson = '';
        $this->statusMessage = 'Configuración de YouTube y voces actualizada.';
    }

    public function render(
        ScheduleAiTodayTvDayAction $scheduleAction,
        ManageTvVideoAction $videoAction,
        SyncTvChannelConfigAction $configAction
    ): View {
        $date = $this->normalizeDate($this->selectedDate);
        $schedule = $scheduleAction->getOrPlanDay($date);
        $query = TvVideo::query()->where('channel_slug', 'ai-today');
        if (in_array($this->videoTypeFilter, ['pill', 'news', 'debate', 'music'], true)) {
            $query->where('type', $this->videoTypeFilter);
        }

        return view('livewire.tv.tv-workspace', [
            'schedule' => $schedule,
            'coverageReport' => $scheduleAction->computeCoverageReport($schedule),
            'videos' => $query->latest()->get(),
            'adSlots' => TvAdSlot::query()->where('channel_slug', 'ai-today')->latest()->get(),
            'channelConfig' => $configAction->getOrCreateConfig('ai-today'),
            'queueStats' => $videoAction->stageCounts('ai-today'),
            'nonCompliantVideos' => TvVideo::query()->where('channel_slug', 'ai-today')->where('broadcast_compliant', false)->get(),
            'nonCompliantAds' => TvAdSlot::query()->where('channel_slug', 'ai-today')->where('broadcast_compliant', false)->get(),
        ])->layoutData([
            'title' => 'Canal TV AI Today',
            'embed' => $this->embed,
        ]);
    }

    protected function normalizeDate(?string $value): string
    {
        $v = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && DateTimeImmutable::createFromFormat('Y-m-d', $v)?->format('Y-m-d') === $v) {
            return $v;
        }

        return now()->toDateString();
    }
}
