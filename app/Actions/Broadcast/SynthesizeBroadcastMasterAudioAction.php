<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Models\BroadcastJob;
use App\Services\Broadcast\OralShotSanitizer;
use App\Services\Broadcast\ShotSubtitleGenerator;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final readonly class SynthesizeBroadcastMasterAudioAction
{
    public function __construct(
        protected TextToSpeechService $ttsService,
        protected ShotSubtitleGenerator $subtitleGenerator,
        protected OralShotSanitizer $sanitizer,
    ) {}

    /**
     * Sintetiza el audio maestro continuo del episodio en una única llamada TTS y calcula
     * las ventanas temporales exactas [start_seconds, duration_seconds] por plano.
     *
     * @return array<string, mixed>
     */
    public function execute(BroadcastJob $job): array
    {
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $existing = $artifacts['master_audio'] ?? null;

        if (
            is_array($existing)
            && ! empty($existing['audio_path'])
            && file_exists((string) $existing['audio_path'])
            && ! empty($existing['timed_shots'])
            && is_array($existing['timed_shots'])
        ) {
            return $existing;
        }

        $shots = is_array($artifacts['shots'] ?? null) ? array_values($artifacts['shots']) : [];
        $fullOralText = $this->resolveFullOralText($job, $artifacts, $shots);
        $voice = $this->resolveOptionalString($artifacts['tts_voice'] ?? null);

        $audioPath = $this->ttsService->synthesize($fullOralText, $voice, 1.0, $job->project);
        $totalDuration = $this->probeAudioDuration($audioPath, $fullOralText);
        $timedShots = $this->distributeShotTimings($shots, $totalDuration);

        $assPath = storage_path("app/private/broadcast/{$job->id}/subtitles/master_subtitles.ass");
        $visualCredit = $this->resolveOptionalString($artifacts['visual_credit'] ?? null);
        $this->subtitleGenerator->generateGlobalAss($timedShots, $assPath, $visualCredit);

        $masterAudio = [
            'audio_path' => $audioPath,
            'ass_path' => $assPath,
            'total_duration_seconds' => $totalDuration,
            'voice' => $voice,
            'timed_shots' => $timedShots,
            'synthesized_at' => now()->toIso8601String(),
        ];

        $artifacts['master_audio'] = $masterAudio;
        $artifacts['shots'] = $timedShots;
        $job->update(['artifacts' => $artifacts]);

        return $masterAudio;
    }

    /**
     * @param  array<string, mixed>  $artifacts
     * @param  list<array<string, mixed>>  $shots
     */
    private function resolveFullOralText(BroadcastJob $job, array $artifacts, array $shots): string
    {
        $fromArtifacts = trim((string) ($artifacts['full_oral_text'] ?? ''));
        if ($fromArtifacts !== '') {
            return $fromArtifacts;
        }

        $fromShots = trim(implode(' ', array_filter(array_map(
            static fn (array $shot): string => trim((string) ($shot['oral_text'] ?? '')),
            $shots
        ))));
        if ($fromShots !== '') {
            return $fromShots;
        }

        $stripped = $this->sanitizer->stripMarkdown((string) ($job->raw_text ?? ''));
        $clean = trim($this->sanitizer->stripLicenseAttribution($stripped)['clean_text']);
        if ($clean !== '') {
            return $clean;
        }

        $title = trim((string) ($job->title ?? ''));
        if ($title === '') {
            throw new RuntimeException("No oral text available for master audio synthesis on job {$job->id}.");
        }

        return $title;
    }

    private function probeAudioDuration(string $audioPath, string $fullOralText): float
    {
        if (! app()->environment('testing') && ! (bool) config('services.filmmaker.mock_ffmpeg', false)) {
            $result = Process::timeout(30)->run([
                'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1', $audioPath,
            ]);

            if ($result->successful()) {
                $probed = (float) trim($result->output());
                if ($probed > 0.0) {
                    return round($probed, 3);
                }
            }
        }

        $words = preg_split('/\s+/u', trim($fullOralText), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return round(max(1.0, count($words) / OralShotSanitizer::WORDS_PER_SECOND), 3);
    }

    /**
     * @param  list<array<string, mixed>>  $shots
     * @return list<array<string, mixed>>
     */
    private function distributeShotTimings(array $shots, float $totalDuration): array
    {
        if ($shots === []) {
            return [];
        }

        $weights = array_map(
            static fn (array $shot): int => max(1, mb_strlen(trim((string) ($shot['oral_text'] ?? '')))),
            $shots
        );
        $totalWeight = max(1, array_sum($weights));
        $cursor = 0.0;
        $lastIndex = count($shots) - 1;
        $timedShots = [];

        foreach ($shots as $index => $shot) {
            $shotDuration = $index === $lastIndex
                ? round(max(0.1, $totalDuration - $cursor), 3)
                : round(max(0.1, $totalDuration * ($weights[$index] / $totalWeight)), 3);

            $timedShots[] = array_merge($shot, [
                'start_seconds' => round($cursor, 3),
                'duration_seconds' => $shotDuration,
            ]);

            $cursor += $shotDuration;
        }

        return $timedShots;
    }

    private function resolveOptionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
