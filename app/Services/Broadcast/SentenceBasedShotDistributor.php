<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Models\Shot;

final readonly class SentenceBasedShotDistributor
{
    public function __construct(
        private OralShotSanitizer $sanitizer,
    ) {}

    /**
     * Distribuye el texto en planos respetando oraciones completas y extrayendo el crédito visual.
     *
     * @param  list<array<string, mixed>|Shot>  $shots
     * @return array{
     *     shots: list<array<string, mixed>>,
     *     visual_credit: ?string,
     *     full_oral_text: string,
     * }
     */
    public function distribute(array $shots, string $rawText, ?string $explicitVisualCredit = null): array
    {
        $stripped = $this->sanitizer->stripMarkdown($rawText);
        $licenseResult = $this->sanitizer->stripLicenseAttribution($stripped);
        $cleanText = $licenseResult['clean_text'];

        $visualCredit = ($explicitVisualCredit !== null && trim($explicitVisualCredit) !== '')
            ? trim($explicitVisualCredit)
            : $licenseResult['extracted_credit'];

        $sentences = $this->splitSentences($cleanText);
        if ($shots === [] || $sentences === []) {
            return [
                'shots' => [],
                'visual_credit' => $visualCredit,
                'full_oral_text' => $cleanText,
            ];
        }

        $effectiveShots = count($sentences) < count($shots)
            ? array_slice($shots, 0, count($sentences))
            : $shots;

        $buckets = $this->distributeSentencesAcrossBuckets($sentences, count($effectiveShots));
        $formattedShots = [];

        foreach ($effectiveShots as $index => $shot) {
            $oralText = $this->sanitizer->clampOralText($buckets[$index]);
            $formattedShots[] = $this->formatShot($shot, $oralText);
        }

        return [
            'shots' => $formattedShots,
            'visual_credit' => $visualCredit,
            'full_oral_text' => $cleanText,
        ];
    }

    /**
     * @return list<string>
     */
    private function splitSentences(string $cleanText): array
    {
        if ($cleanText === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?])\s+/u', $cleanText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = [];

        foreach ($parts as $sentence) {
            $trimmed = trim($sentence);
            if ($trimmed === '') {
                continue;
            }
            foreach ($this->splitLongSentenceIntoClauses($trimmed) as $clause) {
                $result[] = $clause;
            }
        }

        return $result;
    }

    /**
     * Subdivide oraciones largas (>22 palabras) en pausas sintácticas naturales (~12-20 palabras)
     * para mantener un ritmo visual dinámico de 6-8 segundos por plano sin cortar frases en fragmentos mínimos.
     *
     * @return list<string>
     */
    private function splitLongSentenceIntoClauses(string $sentence): array
    {
        $words = preg_split('/\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= 22) {
            return [$sentence];
        }

        $segments = preg_split('/(?<=[,;:])\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY) ?: [$sentence];
        $clauses = [];
        $current = '';

        foreach ($segments as $segment) {
            $candidate = $current === '' ? $segment : "{$current} {$segment}";
            $candidateWords = count(preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $currentWords = $current === '' ? 0 : count(preg_split('/\s+/u', $current, -1, PREG_SPLIT_NO_EMPTY) ?: []);

            if ($current !== '' && $candidateWords > 20 && $currentWords >= 8) {
                $clauses[] = $current;
                $current = $segment;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            if ($clauses !== [] && count(preg_split('/\s+/u', $current, -1, PREG_SPLIT_NO_EMPTY) ?: []) < 7) {
                $clauses[count($clauses) - 1] .= ' '.$current;
            } else {
                $clauses[] = $current;
            }
        }

        return $clauses !== [] ? $clauses : [$sentence];
    }

    /**
     * @param  list<string>  $sentences
     * @return list<string>
     */
    private function distributeSentencesAcrossBuckets(array $sentences, int $bucketCount): array
    {
        $sentenceCount = count($sentences);
        $base = intdiv($sentenceCount, $bucketCount);
        $remainder = $sentenceCount % $bucketCount;
        $buckets = [];
        $offset = 0;

        for ($i = 0; $i < $bucketCount; $i++) {
            $take = $base + ($i < $remainder ? 1 : 0);
            $buckets[] = implode(' ', array_slice($sentences, $offset, $take));
            $offset += $take;
        }

        return $buckets;
    }

    /**
     * @param  array<string, mixed>|Shot  $shot
     * @return array<string, mixed>
     */
    private function formatShot(array|Shot $shot, string $oralText): array
    {
        $words = preg_split('/\s+/u', trim($oralText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $base = is_array($shot) ? $shot : [
            'shot_id' => (string) $shot->id,
            'shot_number' => (int) $shot->shot_number,
            'shot_type' => $shot->shot_type,
        ];

        return array_merge($base, [
            'oral_text' => $oralText,
            'word_count' => count($words),
            'estimated_seconds' => $this->sanitizer->estimateSeconds($oralText),
        ]);
    }
}
