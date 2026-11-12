<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Models\Character;
use App\Models\Project;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Services\JgAgente\AgentClientService;
use App\Services\Pipeline\BatchPipelineProcessor;
use App\Services\Pipeline\BibleSyncService;
use App\Services\Pipeline\ShotsBreakdownService;
use App\Services\Pipeline\SluglineSyncService;
use App\Services\Pipeline\UserLockManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ScriptPipelineService
{
    protected BibleSyncService $bibleSync;

    protected SluglineSyncService $sluglineSync;

    protected ShotsBreakdownService $shotsBreakdown;

    protected BatchPipelineProcessor $batchProcessor;

    protected UserLockManager $lockManager;

    public function __construct(
        protected AgentClientService $agentClient,
        protected ?AgentTelemetryService $telemetryService = null,
        ?BibleSyncService $bibleSync = null,
        ?SluglineSyncService $sluglineSync = null,
        ?ShotsBreakdownService $shotsBreakdown = null,
        ?BatchPipelineProcessor $batchProcessor = null,
        ?UserLockManager $lockManager = null
    ) {
        $this->telemetryService = $this->telemetryService ?? app(AgentTelemetryService::class);
        $this->bibleSync = $bibleSync ?? new BibleSyncService($this->agentClient);
        $this->sluglineSync = $sluglineSync ?? new SluglineSyncService;
        $this->shotsBreakdown = $shotsBreakdown ?? new ShotsBreakdownService($this->agentClient, $this->telemetryService);
        $this->batchProcessor = $batchProcessor ?? new BatchPipelineProcessor;
        $this->lockManager = $lockManager ?? new UserLockManager;
    }

    public function assertUserQuotaAvailable(?Project $project, int $estimatedTokens = 100): void
    {
        if (! $project) {
            return;
        }

        $user = $project->user ?? (auth()->check() ? auth()->user() : null);
        if (! $user) {
            return;
        }

        if (! $user->hasDailyTokensAvailable($estimatedTokens)) {
            $limit = $user->getDailyTokenLimit();
            $available = $user->getTokensAvailableToday();

            throw new QuotaExceededException(
                message: 'Has alcanzado tu cupo diario de tokens de IA ('.number_format($limit, 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.',
                requiredTokens: $estimatedTokens,
                availableTokens: $available
            );
        }
    }

    public function transitionToStructured(ScriptBlock $block): ScriptBlock
    {
        $block->loadMissing('scene.project.characters', 'scene.project.scenes.scriptBlocks', 'scene.location');
        $project = $block->scene?->project;
        if ($project) {
            $this->syncProjectCharacters($project);
            $this->syncProjectLocations($project);
        }

        if ($block->isStageLocked('structured')) {
            return $block;
        }

        $characters = $project ? $project->characters()->get()->map(fn (Character $c) => $c->toAgentContext())->toArray() : [];
        $location = $this->resolveLocationContext($block);
        $rawText = $block->raw_text ?? '';

        $this->assertUserQuotaAvailable($project, max(50, (int) ceil(mb_strlen((string) $rawText) / 4) + 150));
        $characters = $this->inferCharactersFromText($rawText, $characters, $project);

        $userLocks = $this->resolveUserLocks($block);
        $structuredText = $this->agentClient->structureTreatment($rawText, $characters, $location, $this->sanitizeUserLocksForAgent($userLocks));
        $structuredText = $this->capitalizeCharacterNames($structuredText, $characters, 'structured');

        $telemetry = $this->agentClient->getLastTelemetry();
        $updatedBlock = $this->persistTransitionResult($block, 'structured', 'structured_text', $structuredText);

        if ($project) {
            $this->telemetryService->record($project->id, $block->id, 'treatment_structuring', ['raw_text' => $rawText, 'characters' => $characters, 'location' => $location], $structuredText, $telemetry);
        }

        return $updatedBlock->refresh();
    }

    public function transitionToScreenplay(ScriptBlock $block): ScriptBlock
    {
        $block->loadMissing('scene.project.characters', 'scene.project.scenes.scriptBlocks', 'scene.location');
        $project = $block->scene?->project;
        if ($project) {
            $this->syncProjectCharacters($project);
            $this->syncProjectLocations($project);
        }

        if ($block->isStageLocked('screenplay')) {
            return $block;
        }

        $characters = $project ? $project->characters()->get()->map(fn (Character $c) => $c->toAgentContext())->toArray() : [];
        $location = $this->resolveLocationContext($block);
        $sourceText = ! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text;

        $this->assertUserQuotaAvailable($project, max(50, (int) ceil(mb_strlen((string) $sourceText) / 4) + 200));
        $characters = $this->inferCharactersFromText($sourceText, $characters, $project);

        $userLocks = $this->resolveUserLocks($block);
        $screenplayContent = $this->agentClient->formatScreenplay($sourceText, $characters, $this->sanitizeUserLocksForAgent($userLocks), $location);
        $screenplayContent = $this->capitalizeCharacterNames($screenplayContent, $characters, 'screenplay');

        $telemetry = $this->agentClient->getLastTelemetry();
        $updatedBlock = $this->persistTransitionResult($block, 'screenplay', 'screenplay_content', $screenplayContent);

        if ($project) {
            $this->telemetryService->record($project->id, $block->id, 'screenplay_formatter', ['structured_text' => $sourceText, 'characters' => $characters, 'location' => $location], $screenplayContent, $telemetry);
        }

        return $updatedBlock->refresh();
    }

    protected function resolveLocationContext(ScriptBlock $block): ?array
    {
        if (! $block->scene?->location) {
            return null;
        }

        $location = $block->scene->location->toAgentContext();
        if ($block->scene?->time_of_day) {
            $location['time_of_day'] = $block->scene->time_of_day;
        }

        return $location;
    }

    protected function persistTransitionResult(ScriptBlock $block, string $stage, string $contentField, string $content): ScriptBlock
    {
        return DB::transaction(function () use ($block, $stage, $contentField, $content) {
            /** @var ScriptBlock|null $fresh */
            $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->isStageLocked($stage)) {
                return $fresh ?? $block;
            }

            $freshLocks = $this->resolveUserLocks($fresh);
            unset($freshLocks['ai_telemetry']);
            $this->lockManager->archiveStageDiffs($freshLocks, $stage);

            $fresh->update([
                $contentField => $content,
                'user_locks' => $freshLocks,
                'stage' => $stage,
                'lock_version' => ($fresh->lock_version ?? 1) + 1,
            ]);

            return $fresh;
        });
    }

    /**
     * @return Collection<int, Shot>
     */
    public function generateShotsBreakdown(ScriptBlock $block): Collection
    {
        return $this->shotsBreakdown->generateShotsBreakdown($block, $this);
    }

    public function recordManualOverride(ScriptBlock $block, string $stage, string $originalText, string $overrideText): ScriptBlock
    {
        return $this->lockManager->recordManualOverride($block, $stage, $originalText, $overrideText);
    }

    public function resolveUserLocks(ScriptBlock $block): array
    {
        return $this->lockManager->resolveUserLocks($block);
    }

    public function sanitizeUserLocksForAgent(array $userLocks): array
    {
        return $this->lockManager->sanitizeUserLocksForAgent($userLocks);
    }

    public function syncProjectCharacters(Project $project): void
    {
        $this->bibleSync->syncProjectCharacters($project);
    }

    public function inferCharactersFromText(string $text, array $existingCharacters = [], ?Project $project = null): array
    {
        return $this->bibleSync->inferCharactersFromText($text, $existingCharacters, $project);
    }

    public function extractDeterministicCandidates(string $text, array $existingCharacters = []): array
    {
        return $this->bibleSync->extractDeterministicCandidates($text, $existingCharacters);
    }

    public function capitalizeCharacterNames(string $text, array $characters, string $stage = 'structured'): string
    {
        return $this->bibleSync->capitalizeCharacterNames($text, $characters, $stage);
    }

    public function parseSlugline(string $slugline): array
    {
        return $this->sluglineSync->parseSlugline($slugline);
    }

    public function syncProjectLocations(Project $project): void
    {
        $this->sluglineSync->syncProjectLocations($project);
    }

    public function batchStructureBlocks(iterable $blocks): array
    {
        return $this->batchProcessor->batchStructureBlocks($blocks, $this);
    }

    public function batchFormatBlocksToScreenplay(iterable $blocks): array
    {
        return $this->batchProcessor->batchFormatBlocksToScreenplay($blocks, $this);
    }

    public function batchGenerateShots(iterable $blocks): array
    {
        return $this->batchProcessor->batchGenerateShots($blocks, $this);
    }

    public function batchRunFullPipeline(iterable $blocks): array
    {
        return $this->batchProcessor->batchRunFullPipeline($blocks, $this);
    }
}
