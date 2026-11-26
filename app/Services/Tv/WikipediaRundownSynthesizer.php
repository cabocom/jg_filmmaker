<?php

declare(strict_types=1);

namespace App\Services\Tv;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final readonly class WikipediaRundownSynthesizer
{
    public const string VISUAL_CREDIT = 'Fuente: Wikipedia · CC BY-SA 4.0';

    private const array ALLOWED_HOSTS = [
        'es.wikipedia.org' => 'es',
        'en.wikipedia.org' => 'en',
    ];

    /**
     * Validate a Wikipedia article URL and extract its language and title.
     *
     * @return array{lang: string, title: string}
     */
    public function validateWikipediaUrl(string $url): array
    {
        $trimmed = trim($url);
        if (! str_starts_with($trimmed, 'https://')) {
            throw new InvalidArgumentException('Wikipedia URL must use strict https:// scheme.');
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts)) {
            throw new InvalidArgumentException('Malformed Wikipedia URL.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $lang = self::ALLOWED_HOSTS[$host] ?? null;
        if ($lang === null) {
            throw new InvalidArgumentException('Only es.wikipedia.org and en.wikipedia.org are supported.');
        }

        $path = (string) ($parts['path'] ?? '');
        if (! str_starts_with($path, '/wiki/')) {
            throw new InvalidArgumentException('Wikipedia URL path must start with /wiki/.');
        }

        $rawTitle = substr($path, strlen('/wiki/'));
        $title = trim(urldecode($rawTitle));
        if ($title === '' || str_contains($title, '/')) {
            throw new InvalidArgumentException('Wikipedia URL must point to a valid article title.');
        }

        return [
            'lang' => $lang,
            'title' => $title,
        ];
    }

    /**
     * Synthesize a clean spoken rundown script and visual credit from a Wikipedia topic or URL.
     *
     * @return array{title: string, script_text: string, visual_credit: string, sources: list<string>}
     */
    public function synthesize(string $topicOrUrl, string $language = 'es', ?int $targetDurationSeconds = null): array
    {
        $trimmed = trim($topicOrUrl);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Topic or Wikipedia URL cannot be empty.');
        }

        if (preg_match('#^https?://#i', $trimmed) === 1) {
            $parsed = $this->validateWikipediaUrl($trimmed);
            $lang = $parsed['lang'];
            $articleTitle = $parsed['title'];
        } else {
            $lang = in_array(strtolower($language), ['es', 'en'], true) ? strtolower($language) : 'es';
            $articleTitle = $trimmed;
        }

        $slug = str_replace(' ', '_', $articleTitle);
        $endpoint = sprintf('https://%s.wikipedia.org/api/rest_v1/page/summary/%s', $lang, rawurlencode($slug));

        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'JGFilmmakerTV/1.0'])
            ->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Wikipedia summary request failed with status %d.', $response->status()));
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $extract = trim((string) preg_replace('/\s+/u', ' ', (string) ($payload['extract'] ?? '')));
        if ($extract === '') {
            throw new RuntimeException('Wikipedia summary response did not contain an extract.');
        }

        if ($targetDurationSeconds !== null && $targetDurationSeconds > 0) {
            $targetWords = max(25, (int) round(max(15, $targetDurationSeconds) * 2.5));
            if (str_word_count($extract) < (int) ($targetWords * 0.8)) {
                $expanded = $this->fetchExpandedExtract($lang, $articleTitle);
                if ($expanded !== '' && str_word_count($expanded) > str_word_count($extract)) {
                    $extract = $expanded;
                }
            }

            $extract = $this->trimToTargetWords($extract, $targetWords);
        }

        $resolvedTitle = trim((string) ($payload['title'] ?? str_replace('_', ' ', $articleTitle)));
        $sourceUrl = trim((string) data_get($payload, 'content_urls.desktop.page', ''));
        if ($sourceUrl === '') {
            $sourceUrl = sprintf('https://%s.wikipedia.org/wiki/%s', $lang, rawurlencode($slug));
        }

        return [
            'title' => $resolvedTitle,
            'script_text' => $extract,
            'visual_credit' => self::VISUAL_CREDIT,
            'sources' => [$sourceUrl],
        ];
    }

    private function fetchExpandedExtract(string $lang, string $articleTitle): string
    {
        try {
            $apiEndpoint = sprintf('https://%s.wikipedia.org/w/api.php', $lang);
            $res = Http::timeout(15)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'JGFilmmakerTV/1.0'])
                ->get($apiEndpoint, [
                    'action' => 'query',
                    'prop' => 'extracts',
                    'explaintext' => 1,
                    'exchars' => 4500,
                    'redirects' => 1,
                    'titles' => $articleTitle,
                    'format' => 'json',
                ]);
        } catch (\Throwable) {
            return '';
        }

        if (! $res->successful()) {
            return '';
        }

        $pages = data_get($res->json(), 'query.pages', []);
        if (! is_array($pages) || $pages === []) {
            return '';
        }

        $firstPage = reset($pages);
        $raw = is_array($firstPage) ? (string) ($firstPage['extract'] ?? '') : '';
        $clean = preg_replace('/^=+[^=]+=+\s*$/m', ' ', $raw) ?? $raw;

        return trim((string) preg_replace('/\s+/u', ' ', $clean));
    }

    private function trimToTargetWords(string $text, int $targetWords): string
    {
        if (str_word_count($text) <= (int) ceil($targetWords * 1.15)) {
            return $text;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $selected = [];
        $words = 0;

        foreach ($sentences as $sentence) {
            $count = str_word_count($sentence);
            if ($words > 0 && ($words + $count) > (int) ceil($targetWords * 1.15)) {
                break;
            }
            $selected[] = $sentence;
            $words += $count;
            if ($words >= $targetWords) {
                break;
            }
        }

        return $selected !== [] ? implode(' ', $selected) : $text;
    }
}
