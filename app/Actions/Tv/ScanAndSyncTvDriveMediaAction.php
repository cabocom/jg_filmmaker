<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Actions\Broadcast\ScanTvMediaDirectoryAction;
use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use App\Models\TvAdSlot;
use App\Models\TvMediaNormalization;
use App\Models\TvRundown;
use App\Models\TvVideo;
use Throwable;

final class ScanAndSyncTvDriveMediaAction
{
    public function __construct(
        private readonly ScanTvMediaDirectoryAction $scanDirectoryAction,
        private readonly SyncTvChannelConfigAction $configAction,
        private readonly AutoGenerateBroadcastShotsAction $broadcastAction,
    ) {}

    public function refreshActiveJobsOnly(string $channelSlug = 'ai-today'): void
    {
        $this->refreshActiveRundowns($channelSlug);
        $this->refreshActiveVideos($channelSlug);
        $this->refreshActiveAds($channelSlug);
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $channelSlug = 'ai-today', bool $forceRefresh = false): array
    {
        try {
            $config = $this->configAction->getOrCreateConfig($channelSlug);
            $profile = (string) ($config->broadcast_profile ?: '720p30');
            $videosDir = (string) ($config->videos_dir ?: 'ai-today/videos');
            $adsDir = (string) ($config->ads_dir ?: 'ai-today/ads');

            $this->refreshActiveJobsOnly($channelSlug);

            $videosScan = $this->scanDirectoryAction->execute($videosDir, $profile, $forceRefresh);
            $videoFiles = (array) ($videosScan['files'] ?? []);
            $nonCompliantCount = $this->syncScannedVideos($channelSlug, $videoFiles, $profile);

            $adsScan = $this->scanDirectoryAction->execute($adsDir, $profile, $forceRefresh);
            $adsFiles = (array) ($adsScan['files'] ?? []);
            $availableAdFiles = $this->syncScannedAds($channelSlug, $adsFiles, $profile);

            $config->update(['last_scanned_at' => now()]);

            return [
                'success' => true,
                'videos_scanned' => count($videoFiles),
                'ads_scanned' => count($availableAdFiles),
                'non_compliant_videos' => $nonCompliantCount,
                'available_ad_files' => $availableAdFiles,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function refreshActiveRundowns(string $channelSlug): void
    {
        $rundowns = TvRundown::query()
            ->where('channel_slug', $channelSlug)
            ->whereIn('stage', ['queued', 'rendering'])
            ->get();

        foreach ($rundowns as $rundown) {
            $jobId = (string) ($rundown->broadcast_job_id ?: $rundown->filmmaker_job_id ?: '');
            if ($jobId === '') {
                continue;
            }
            $job = BroadcastJob::query()->find($jobId);
            if ($job !== null) {
                $this->applyBroadcastJobStatus($rundown, $job, 'relative_media_path');
            }
        }
    }

    private function refreshActiveVideos(string $channelSlug): void
    {
        $videos = TvVideo::query()
            ->where('channel_slug', $channelSlug)
            ->whereIn('stage', ['queued', 'rendering', 'normalizing'])
            ->get();

        foreach ($videos as $video) {
            $isNorm = $video->stage === 'normalizing' || ! empty($video->normalization_job_id);
            if ($isNorm && ! empty($video->normalization_job_id)) {
                $norm = TvMediaNormalization::query()->find((string) $video->normalization_job_id);
                if ($norm !== null) {
                    $this->applyNormalizationStatus($video, $norm);
                }

                continue;
            }

            $jobId = (string) ($video->broadcast_job_id ?: $video->filmmaker_job_id ?: '');
            if ($jobId !== '') {
                $job = BroadcastJob::query()->find($jobId);
                if ($job !== null) {
                    $this->applyBroadcastJobStatus($video, $job, 'relative_media_path');
                }
            }
        }
    }

    private function refreshActiveAds(string $channelSlug): void
    {
        $ads = TvAdSlot::query()
            ->where('channel_slug', $channelSlug)
            ->whereIn('stage', ['queued', 'normalizing'])
            ->whereNotNull('normalization_job_id')
            ->get();

        foreach ($ads as $ad) {
            $norm = TvMediaNormalization::query()->find((string) $ad->normalization_job_id);
            if ($norm !== null) {
                $this->applyNormalizationStatus($ad, $norm);
            }
        }
    }

    private function applyNormalizationStatus(TvVideo|TvAdSlot $model, TvMediaNormalization $norm): void
    {
        if (in_array($norm->stage, ['ready', 'completed'], true)) {
            $probeAfter = is_array($norm->probe_after) ? $norm->probe_after : [];
            $model->update([
                'stage' => 'ready',
                'error_message' => null,
                'broadcast_compliant' => (bool) ($probeAfter['broadcast_compliant'] ?? true),
                'compliance_reasons_json' => [],
                'normalization_job_id' => null,
            ]);
        } elseif ($norm->stage === 'failed') {
            $model->update([
                'stage' => 'failed',
                'error_message' => (string) ($norm->error_message ?: 'Fallo en normalización'),
                'normalization_job_id' => null,
            ]);
        }
    }

    private function applyBroadcastJobStatus(TvVideo|TvRundown $model, BroadcastJob $job, string $pathField): void
    {
        $stage = $job->stage instanceof BroadcastJobStage ? $job->stage->value : (string) $job->stage;

        if (in_array($stage, ['completed', 'ready'], true)) {
            $updates = ['stage' => 'ready', 'error_message' => null];
            if ($model instanceof TvRundown) {
                $updates['status'] = 'ready';
            } else {
                $updates['status'] = 'ready';
                $relPath = data_get($job->exports, 'tv_drive_relative_path');
                if (is_string($relPath) && $relPath !== '') {
                    $updates[$pathField] = $relPath;
                    $updates['mp4_url'] = $relPath;
                    $updates['file_path'] = $relPath;
                    $updates['broadcast_compliant'] = true;
                }
            }
            $model->update($updates);
        } elseif ($stage === 'failed') {
            $updates = [
                'stage' => 'failed',
                'status' => 'failed',
                'error_message' => (string) ($job->error_message ?: 'Error en render broadcast'),
            ];
            $model->update($updates);
        } elseif ($stage === 'shots' && $model->stage === 'queued') {
            $this->broadcastAction->startRendering($job);
            $model->update(['stage' => 'rendering', 'status' => 'rendering']);
        } elseif (in_array($stage, ['rendering', 'assembling', 'audio', 'visuals'], true) && $model->stage !== 'rendering') {
            $model->update(['stage' => 'rendering', 'status' => 'rendering']);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function syncScannedVideos(string $channelSlug, array $files, string $profile): int
    {
        $existing = TvVideo::query()->where('channel_slug', $channelSlug)->get()->keyBy('relative_media_path');
        $nonCompliantCount = 0;

        foreach ($files as $file) {
            $path = (string) ($file['relative_path'] ?? '');
            if ($path === '') {
                continue;
            }

            $compliant = (bool) ($file['broadcast_compliant'] ?? false);
            if (! $compliant) {
                $nonCompliantCount++;
            }
            $reasons = (array) ($file['reasons'] ?? $file['compliance_reasons'] ?? []);
            $hasDuration = isset($file['duration_seconds']) && is_numeric($file['duration_seconds']);
            $duration = $hasDuration ? (int) max(0, round((float) $file['duration_seconds'])) : 0;
            $stage = ! empty($file['normalizing']) ? 'normalizing' : 'ready';

            if ($existing->has($path)) {
                /** @var TvVideo $video */
                $video = $existing->get($path);
                $video->update([
                    'broadcast_compliant' => $compliant,
                    'compliance_profile' => $profile,
                    'compliance_reasons_json' => $reasons,
                    'duration_seconds' => $hasDuration ? $duration : (int) $video->duration_seconds,
                    'mp4_url' => $path,
                ]);
            } else {
                TvVideo::create([
                    'channel_slug' => $channelSlug,
                    'origin' => 'drive',
                    'type' => 'pill',
                    'title' => pathinfo($path, PATHINFO_FILENAME),
                    'relative_media_path' => $path,
                    'mp4_url' => $path,
                    'file_path' => $path,
                    'broadcast_compliant' => $compliant,
                    'compliance_profile' => $profile,
                    'compliance_reasons_json' => $reasons,
                    'duration_seconds' => $duration,
                    'stage' => $stage,
                    'status' => $stage,
                ]);
            }
        }

        return $nonCompliantCount;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, string>
     */
    private function syncScannedAds(string $channelSlug, array $files, string $profile): array
    {
        $existing = TvAdSlot::query()->where('channel_slug', $channelSlug)->get()->keyBy('media_path');
        $availableAdFiles = array_values(array_filter(array_map(
            fn (array $f): string => (string) ($f['relative_path'] ?? ''),
            $files
        ), fn (string $p): bool => $p !== ''));

        foreach ($files as $file) {
            $path = (string) ($file['relative_path'] ?? '');
            if ($path === '' || ! $existing->has($path)) {
                continue;
            }

            /** @var TvAdSlot $slot */
            $slot = $existing->get($path);
            $hasDuration = isset($file['duration_seconds']) && is_numeric($file['duration_seconds']);
            $duration = $hasDuration ? (int) max(0, round((float) $file['duration_seconds'])) : 0;

            $slot->update([
                'broadcast_compliant' => (bool) ($file['broadcast_compliant'] ?? false),
                'compliance_profile' => $profile,
                'compliance_reasons_json' => (array) ($file['reasons'] ?? $file['compliance_reasons'] ?? []),
                'duration_seconds' => $duration > 0 ? $duration : (int) $slot->duration_seconds,
            ]);
        }

        return $availableAdFiles;
    }
}
