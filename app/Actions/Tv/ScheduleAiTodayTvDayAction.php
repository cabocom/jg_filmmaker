<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Models\TvAdSlot;
use App\Models\TvChannelConfig;
use App\Models\TvDaySchedule;
use App\Models\TvVideo;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class ScheduleAiTodayTvDayAction
{
    private function assertValidDate(string $date): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || DateTimeImmutable::createFromFormat('Y-m-d', $date)?->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Fecha inválida: [{$date}]. Se espera formato Y-m-d.");
        }
    }

    public function assertScheduleUnlockedForPlanning(string $channelSlug = 'ai-today'): void
    {
        $config = TvChannelConfig::query()->where('channel_slug', $channelSlug)->first();
        if ($config instanceof TvChannelConfig && (bool) $config->planning_in_progress) {
            throw new RuntimeException("La planificación del canal [{$channelSlug}] está bloqueada por un proceso en curso.", 409);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function assertBlocksUseEligibleAssets(array $blocks, string $channelSlug = 'ai-today'): void
    {
        $videoIds = array_values(array_unique(array_filter(array_column($blocks, 'video_id'), fn ($id): bool => ! empty($id))));
        $adIds = array_values(array_unique(array_filter(array_column($blocks, 'ad_slot_id'), fn ($id): bool => ! empty($id))));

        $videos = $videoIds !== [] ? TvVideo::query()->where('channel_slug', $channelSlug)->whereIn('id', $videoIds)->get()->keyBy('id') : collect();
        foreach ($videoIds as $id) {
            $video = $videos->get($id);
            if (! $video instanceof TvVideo || $video->isBusy() || ! (bool) $video->broadcast_compliant) {
                throw new InvalidArgumentException("El vídeo [{$id}] no existe, está ocupado o no cumple normas broadcast.");
            }
        }

        $ads = $adIds !== [] ? TvAdSlot::query()->where('channel_slug', $channelSlug)->whereIn('id', $adIds)->get()->keyBy('id') : collect();
        foreach ($adIds as $id) {
            $ad = $ads->get($id);
            if (! $ad instanceof TvAdSlot || $ad->isBusy() || ! (bool) $ad->broadcast_compliant) {
                throw new InvalidArgumentException("El slot publicitario [{$id}] no existe, está ocupado o no cumple normas broadcast.");
            }
        }
    }

    public function getOrPlanDay(string $date, string $channelSlug = 'ai-today'): TvDaySchedule
    {
        $this->assertValidDate($date);

        $existing = TvDaySchedule::query()
            ->where('channel_slug', $channelSlug)
            ->whereDate('schedule_date', $date)
            ->first();

        if ($existing instanceof TvDaySchedule) {
            return $existing;
        }

        $result = $this->proposeAiDay($date, false, $channelSlug);

        return $result['schedule'];
    }

    /**
     * @return array<string, mixed>
     */
    public function proposeAiDay(string $date, bool $forceReplan = false, string $channelSlug = 'ai-today'): array
    {
        $this->assertValidDate($date);
        $this->assertScheduleUnlockedForPlanning($channelSlug);

        $existing = TvDaySchedule::query()
            ->where('channel_slug', $channelSlug)
            ->whereDate('schedule_date', $date)
            ->first();

        if ($existing instanceof TvDaySchedule && $existing->locked_by_admin && ! $forceReplan) {
            return [
                'success' => false,
                'locked' => true,
                'schedule' => $existing,
                'error' => 'Parrilla bloqueada manualmente por el administrador.',
            ];
        }

        $blocks = $this->buildDefault24hBlocks();
        if ($existing instanceof TvDaySchedule) {
            $existing->update([
                'locked_by_admin' => false,
                'blocks_json' => $blocks,
                'slots' => $blocks,
            ]);
            $schedule = $existing->fresh() ?? $existing;
        } else {
            $schedule = TvDaySchedule::create([
                'channel_slug' => $channelSlug,
                'schedule_date' => $date,
                'air_date' => $date,
                'locked_by_admin' => false,
                'blocks_json' => $blocks,
                'slots' => $blocks,
            ]);
        }

        return ['success' => true, 'locked' => false, 'schedule' => $schedule];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function saveManualBlocks(string $date, array $blocks, string $channelSlug = 'ai-today'): TvDaySchedule
    {
        $this->assertValidDate($date);
        $this->assertScheduleUnlockedForPlanning($channelSlug);
        $this->assertBlocksUseEligibleAssets($blocks, $channelSlug);

        $existing = TvDaySchedule::query()
            ->where('channel_slug', $channelSlug)
            ->whereDate('schedule_date', $date)
            ->first();

        if ($existing instanceof TvDaySchedule) {
            $existing->update([
                'locked_by_admin' => true,
                'blocks_json' => $blocks,
                'slots' => $blocks,
            ]);

            return $existing->fresh() ?? $existing;
        }

        return TvDaySchedule::create([
            'channel_slug' => $channelSlug,
            'schedule_date' => $date,
            'air_date' => $date,
            'locked_by_admin' => true,
            'blocks_json' => $blocks,
            'slots' => $blocks,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function computeCoverageReport(TvDaySchedule $schedule): array
    {
        $blocks = is_array($schedule->blocks_json) ? $schedule->blocks_json : (is_array($schedule->slots) ? $schedule->slots : []);
        $intervals = $this->extractSortedIntervals($blocks);
        $gaps = $this->collectCoverageGaps($intervals);

        $gapMinutes = (int) array_sum(array_column($gaps, 'duration_minutes'));
        $coveredMinutes = max(0, 1440 - $gapMinutes);
        $percentage = round(($coveredMinutes / 1440) * 100, 2);

        $pendingStages = ['pending', 'queued', 'rendering'];
        $pendingRenders = 0;
        foreach ($blocks as $block) {
            if (in_array(strtolower((string) ($block['stage'] ?? '')), $pendingStages, true)) {
                $pendingRenders++;
            }
        }

        return [
            'total_minutes' => 1440,
            'covered_minutes' => $coveredMinutes,
            'coverage_percentage' => $percentage,
            'coverage_percent' => $percentage,
            'is_complete' => $coveredMinutes >= 1440 && empty($gaps),
            'is_complete_24h' => $coveredMinutes >= 1440 && empty($gaps),
            'gaps' => $gaps,
            'gaps_count' => count($gaps),
            'pending_renders' => $pendingRenders,
            'total_blocks' => count($blocks),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array{start: int, end: int}>
     */
    private function extractSortedIntervals(array $blocks): array
    {
        $intervals = [];
        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($block['start_time'], $block['end_time'])) {
                continue;
            }
            $start = $this->timeToMinutes((string) $block['start_time']);
            $end = $this->timeToMinutes((string) $block['end_time']);
            if ($end > $start) {
                $intervals[] = ['start' => max(0, min(1440, $start)), 'end' => max(0, min(1440, $end))];
            }
        }
        usort($intervals, fn (array $a, array $b): int => $a['start'] <=> $b['start'] ?: $a['end'] <=> $b['end']);

        return $intervals;
    }

    /**
     * @param  array<int, array{start: int, end: int}>  $intervals
     * @return array<int, array<string, mixed>>
     */
    private function collectCoverageGaps(array $intervals): array
    {
        $gaps = [];
        $cursor = 0;
        foreach ($intervals as $interval) {
            if ($interval['start'] > $cursor) {
                $start = $this->minutesToTime($cursor);
                $end = $this->minutesToTime($interval['start']);
                $gaps[] = [
                    'start' => $start, 'end' => $end, 'start_time' => $start,
                    'end_time' => $end, 'duration_minutes' => $interval['start'] - $cursor,
                ];
            }
            $cursor = max($cursor, $interval['end']);
        }
        if ($cursor < 1440) {
            $start = $this->minutesToTime($cursor);
            $gaps[] = [
                'start' => $start, 'end' => '24:00', 'start_time' => $start,
                'end_time' => '24:00', 'duration_minutes' => 1440 - $cursor,
            ];
        }

        return $gaps;
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', trim($time));

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildDefault24hBlocks(): array
    {
        $defs = [
            ['00:00', '06:00', 'debate', 'Debate Nocturno - Redifusión'], ['06:00', '08:00', 'news', 'Informativo Matinal AI Today'],
            ['08:00', '09:00', 'pill', 'Píldora Tecnológica de la Mañana'], ['09:00', '09:30', 'ad', 'Espacio Promocional'],
            ['09:30', '13:00', 'debate', 'Mesa Redonda sobre Agentes IA'], ['13:00', '15:00', 'news', 'Edición Mediodía AI Today'],
            ['15:00', '16:00', 'pill', 'Píldora de Actualidad y Herramientas'], ['16:00', '16:30', 'ad', 'Espacio Promocional'],
            ['16:30', '20:00', 'debate', 'Debate de la Tarde: Ecosistema y Modelos'], ['20:00', '22:00', 'news', 'Informativo Central de la Noche'],
            ['22:00', '23:30', 'news', 'Resumen de la Jornada - Redifusión'], ['23:30', '24:00', 'pill', 'Píldora de Cierre'],
        ];

        return array_map(fn (array $r, int $i): array => [
            'id' => 'block-'.($i + 1),
            'start_time' => $r[0],
            'end_time' => $r[1],
            'type' => $r[2],
            'title' => $r[3],
            'stage' => 'pending',
        ], $defs, array_keys($defs));
    }
}
