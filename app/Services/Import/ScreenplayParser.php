<?php

declare(strict_types=1);

namespace App\Services\Import;

use InvalidArgumentException;

class ScreenplayParser
{
    protected const SLUGLINE_REGEX = '/^(?:(?:\d+[\.\-\s]+|\bESCENA\s+\d+[:\s\-\.]*)\s*)?(INT\.|EXT\.|INT\/EXT\.|INT\.\/EXT\.|I\/E\.|EST\.)\s+(.+?)(?:(?:\s*[\-\–]\s*|\s+)(D[IÍ]A|NOCHE|TARDE|MAÑANA|CONTINUO|CONTINUA|MOMENTOS DESPU[EÉ]S|SUNSET|DAWN|NIGHT|DAY))?(?:\s*[\-\–\s]+(\d+))?$/iu';

    protected const CHARACTER_REGEX = '/^[A-ZÁÉÍÓÚÑ0-9\s\.\-_]{2,35}(?:\s*\(.*?\))?$/u';

    protected const TRANSITION_WORDS = ['CORTE A:', 'FUNDIDO A:', 'DISUELVE A:', 'FADE IN:', 'FADE OUT:', 'SMASH CUT:', 'MATCH CUT:'];

    public function __construct(
        protected ?ScriptFileExtractor $extractor = null
    ) {
        $this->extractor = $extractor ?? new ScriptFileExtractor;
    }

    /**
     * @return array{scenes_count: int, blocks_count: int, characters: list<string>, locations: list<string>, scenes: list<array>, preview: array}
     */
    public function parse(string $text): array
    {
        $normalizedText = $this->extractor->normalizeEncoding($text);
        if (empty($normalizedText)) {
            throw new InvalidArgumentException('El archivo de guión está vacío.');
        }

        $lines = explode("\n", $normalizedText);
        $scenes = [];
        $uniqueCharacters = [];
        $uniqueLocations = [];
        $state = $this->initState();

        foreach ($lines as $line) {
            $this->processLine(trim($line), $line, $scenes, $uniqueCharacters, $uniqueLocations, $state);
        }

        $this->flushCurrentScene($scenes, $state);

        return $this->buildResult($scenes, $uniqueCharacters, $uniqueLocations, $normalizedText);
    }

    protected function initState(): array
    {
        return [
            'currentScene' => null,
            'currentBlocks' => [],
            'currentBlockLines' => [],
            'currentBlockType' => 'action',
            'sceneOrder' => 1,
            'blockOrder' => 1,
        ];
    }

    protected function processLine(
        string $trimmedLine,
        string $rawLine,
        array &$scenes,
        array &$characters,
        array &$locations,
        array &$state
    ): void {
        if (preg_match(self::SLUGLINE_REGEX, $trimmedLine, $matches)) {
            $this->flushCurrentScene($scenes, $state);
            $this->startNewScene($matches, $locations, $state);

            return;
        }

        if ($state['currentScene'] === null && ! empty($trimmedLine)) {
            $this->initDefaultScene($locations, $state);
        }

        if ($state['currentScene'] === null) {
            return;
        }

        if ($this->isCharacterLine($trimmedLine)) {
            $this->handleCharacterLine($trimmedLine, $characters, $state);

            return;
        }

        $this->handleContentLine($trimmedLine, $rawLine, $state);
    }

    protected function isCharacterLine(string $trimmed): bool
    {
        return ! empty($trimmed)
            && preg_match(self::CHARACTER_REGEX, $trimmed)
            && ! in_array($trimmed, self::TRANSITION_WORDS, true)
            && mb_strtoupper($trimmed, 'UTF-8') === $trimmed;
    }

    protected function handleCharacterLine(string $trimmed, array &$characters, array &$state): void
    {
        $rawCharName = preg_replace('/\s*\(.*?\)\s*/', '', $trimmed);
        $clean = trim((string) $rawCharName);
        if (mb_strlen($clean) >= 2 && ! is_numeric($clean)) {
            $characters[] = $clean;
        }

        $this->flushCurrentBlock($state);
        $state['currentBlockType'] = 'dialogue';
        $state['currentBlockLines'][] = $trimmed;
    }

    protected function handleContentLine(string $trimmed, string $raw, array &$state): void
    {
        if (empty($trimmed)) {
            if (count($state['currentBlockLines']) >= 4) {
                $this->flushCurrentBlock($state);
            }

            return;
        }

        $state['currentBlockLines'][] = $raw;
    }

    protected function startNewScene(array $matches, array &$locations, array &$state): void
    {
        $prefix = strtoupper(trim($matches[1]));
        $locPart = trim($matches[2] ?? 'GENERAL');
        $timePart = $this->normalizeTimeOfDay($matches[3] ?? 'DÍA');

        $locClean = preg_replace('/\s+/', ' ', $locPart);
        $locClean = trim(rtrim($locClean, "-–. \t\n\r\0\x0B")) ?: 'GENERAL';

        $order = $state['sceneOrder']++;
        $slugline = "{$prefix} {$locClean} - {$timePart} {$order}";
        $locations[] = $locClean;

        $state['blockOrder'] = 1;
        $state['currentScene'] = [
            'scene_order' => $order,
            'slugline' => $slugline,
            'location_name' => $locClean,
            'time_of_day' => $timePart,
            'blocks' => [],
        ];
    }

    protected function normalizeTimeOfDay(string $time): string
    {
        $timePart = strtoupper(trim($time));
        if (str_contains($timePart, 'DIA') || str_contains($timePart, 'DÍA') || $timePart === 'DAY') {
            return 'DÍA';
        }
        if (str_contains($timePart, 'NOCHE') || $timePart === 'NIGHT') {
            return 'NOCHE';
        }
        if (str_contains($timePart, 'TARDE')) {
            return 'TARDE';
        }
        if (str_contains($timePart, 'MAÑANA')) {
            return 'MAÑANA';
        }

        return 'CONTINUO';
    }

    protected function initDefaultScene(array &$locations, array &$state): void
    {
        $state['currentScene'] = [
            'scene_order' => $state['sceneOrder']++,
            'slugline' => 'INT. GENERAL - DÍA 1',
            'location_name' => 'GENERAL',
            'time_of_day' => 'DÍA',
            'blocks' => [],
        ];
        $locations[] = 'GENERAL';
    }

    protected function flushCurrentBlock(array &$state): void
    {
        if (empty($state['currentBlockLines'])) {
            return;
        }

        $raw = trim(implode("\n", $state['currentBlockLines']));
        if (! empty($raw)) {
            $state['currentBlocks'][] = [
                'block_order' => $state['blockOrder']++,
                'raw_text' => $raw,
                'screenplay_content' => $raw,
                'type' => $state['currentBlockType'],
            ];
        }

        $state['currentBlockLines'] = [];
        $state['currentBlockType'] = 'action';
    }

    protected function flushCurrentScene(array &$scenes, array &$state): void
    {
        $this->flushCurrentBlock($state);
        if ($state['currentScene'] === null) {
            return;
        }

        if (empty($state['currentBlocks'])) {
            $state['currentBlocks'][] = [
                'block_order' => 1,
                'raw_text' => $state['currentScene']['slugline'],
                'screenplay_content' => $state['currentScene']['slugline'],
                'type' => 'action',
            ];
        }

        $state['currentScene']['blocks'] = $state['currentBlocks'];
        $scenes[] = $state['currentScene'];
        $state['currentBlocks'] = [];
    }

    protected function buildResult(array $scenes, array $characters, array $locations, string $text): array
    {
        $uniqueCharacters = array_values(array_unique(array_filter($characters)));
        $uniqueLocations = array_values(array_unique(array_filter($locations)));

        $totalBlocks = 0;
        foreach ($scenes as $sc) {
            $totalBlocks += count($sc['blocks']);
        }

        return [
            'scenes_count' => count($scenes),
            'blocks_count' => $totalBlocks,
            'characters' => $uniqueCharacters,
            'locations' => $uniqueLocations,
            'scenes' => $scenes,
            'preview' => [
                'first_slugline' => $scenes[0]['slugline'] ?? null,
                'snippet' => mb_substr($text, 0, 800),
            ],
        ];
    }
}
