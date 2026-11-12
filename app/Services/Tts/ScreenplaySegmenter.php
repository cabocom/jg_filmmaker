<?php

declare(strict_types=1);

namespace App\Services\Tts;

use App\Models\Character;
use App\Models\Project;
use App\Models\ScriptBlock;
use Illuminate\Support\Collection;

class ScreenplaySegmenter
{
    protected CharacterVoiceMapper $voiceMapper;

    public function __construct(
        protected string $defaultVoice,
        protected bool $hasGoogleApiKey,
        ?CharacterVoiceMapper $voiceMapper = null
    ) {
        $this->voiceMapper = $voiceMapper ?? new CharacterVoiceMapper($this->hasGoogleApiKey);
    }

    public function expandScreenplayAbbreviations(string $text): string
    {
        $expanded = preg_replace('/(^|[\r\n\s])INT\.?\s*[\/\-]\s*EXT\.?\s*/ui', '$1Interior y exterior, ', $text);
        $expanded = preg_replace('/(^|[\r\n\s])I\.?\s*[\/\-]\s*E\.?\s*/ui', '$1Interior y exterior, ', $expanded);
        $expanded = preg_replace('/(^|[\r\n\s])INT\.\s*/ui', '$1Interior, ', $expanded);
        $expanded = preg_replace('/(^|[\r\n\s])EXT\.\s*/ui', '$1Exterior, ', $expanded);
        $expanded = preg_replace('/(^|[\r\n\s])EST\.\s*/ui', '$1Estudio, ', $expanded);

        return trim($expanded);
    }

    public function parseBlockToSegments(ScriptBlock $block, Collection|array $characters = [], ?Project $project = null): array
    {
        $project ??= $block->relationLoaded('scene') ? $block->scene?->project : null;
        $characterVoiceMap = $this->voiceMapper->buildCharacterVoiceMap($characters);

        if (in_array($block->stage, ['screenplay', 'shots'], true) && ! empty($block->screenplay_content)) {
            return $this->parseScreenplayText($block->screenplay_content, $characterVoiceMap, $project);
        }

        $prose = ($block->stage === 'raw')
            ? ($block->raw_text ?: $block->structured_text)
            : ($block->structured_text ?: $block->raw_text);
        $cleanProse = trim((string) $prose);

        if ($cleanProse === '') {
            return [];
        }

        $candidateSegments = $this->parseScreenplayText($cleanProse, $characterVoiceMap, $project);
        if (collect($candidateSegments)->contains('type', 'dialogue')) {
            return $candidateSegments;
        }

        return [['type' => 'narrator', 'speaker' => 'NARRADOR', 'voice' => $this->defaultVoice, 'text' => $cleanProse]];
    }

    public function parseScreenplayText(string $text, array &$voiceMap, ?Project $project = null): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", trim($text)));
        $segments = [];
        $currentSpeaker = null;
        $dialogueLines = [];
        $actionLines = [];
        $inDialogue = false;

        foreach ($lines as $raw) {
            $this->processScreenplayLine(trim($raw), $segments, $currentSpeaker, $dialogueLines, $actionLines, $inDialogue, $voiceMap, $project);
        }

        $this->flushDialogue($segments, $currentSpeaker, $dialogueLines, $voiceMap, $project);
        $this->flushAction($segments, $actionLines);

        return $segments;
    }

    protected function processScreenplayLine(
        string $line,
        array &$segments,
        ?string &$currentSpeaker,
        array &$dialogueLines,
        array &$actionLines,
        bool &$inDialogue,
        array &$voiceMap,
        ?Project $project
    ): void {
        if ($line === '') {
            $this->handleEmptyLine($inDialogue, $segments, $currentSpeaker, $dialogueLines, $voiceMap, $project);

            return;
        }

        if (! $inDialogue && $this->matchInlineDialogue($line, $segments, $actionLines, $currentSpeaker, $dialogueLines)) {
            $this->flushDialogue($segments, $currentSpeaker, $dialogueLines, $voiceMap, $project);

            return;
        }

        if (! $inDialogue && $this->isCharacterLine($line)) {
            $this->flushAction($segments, $actionLines);
            $currentSpeaker = $line;
            $inDialogue = true;

            return;
        }

        if ($inDialogue) {
            $this->appendDialogueLine($line, $dialogueLines);

            return;
        }

        $actionLines[] = $line;
    }

    protected function handleEmptyLine(
        bool &$inDialogue,
        array &$segments,
        ?string &$currentSpeaker,
        array &$dialogueLines,
        array &$voiceMap,
        ?Project $project
    ): void {
        if ($inDialogue) {
            $this->flushDialogue($segments, $currentSpeaker, $dialogueLines, $voiceMap, $project);
            $inDialogue = false;
        }
    }

    protected function appendDialogueLine(string $line, array &$dialogueLines): void
    {
        if (! (str_starts_with($line, '(') && str_ends_with($line, ')'))) {
            $dialogueLines[] = $line;
        }
    }

    protected function matchInlineDialogue(string $line, array &$segments, array &$actionLines, ?string &$speaker, array &$dialogueLines): bool
    {
        if (preg_match('/^([A-ZÁÉÍÓÚÑ0-9\s\.\-\'\’]+?)(?:\s*\([^\)]+\))?:\s*(.+)$/u', $line, $match)) {
            $possible = trim($match[1]);
            if ($this->isCharacterLine($possible)) {
                $this->flushAction($segments, $actionLines);
                $speaker = $possible;
                $dialogueLines[] = trim($match[2]);

                return true;
            }
        }

        return false;
    }

    protected function flushAction(array &$segments, array &$actionLines): void
    {
        if (! empty($actionLines)) {
            $text = trim(implode("\n", $actionLines));
            if ($text !== '') {
                $segments[] = ['type' => 'narrator', 'speaker' => 'NARRADOR', 'voice' => $this->defaultVoice, 'text' => $text];
            }
            $actionLines = [];
        }
    }

    protected function flushDialogue(array &$segments, ?string &$speaker, array &$dialogueLines, array &$voiceMap, ?Project $project): void
    {
        if ($speaker === null || empty($dialogueLines)) {
            $speaker = null;
            $dialogueLines = [];

            return;
        }

        $text = trim(implode(' ', $dialogueLines));
        if ($text !== '') {
            $cleanSpeaker = rtrim(strtoupper(trim(preg_replace('/\s*\([^\)]+\)$/u', '', $speaker))), '.');
            $voice = $this->resolveVoiceForSpeaker($cleanSpeaker, $voiceMap, $project);
            $segments[] = ['type' => 'dialogue', 'speaker' => $cleanSpeaker, 'voice' => $voice, 'text' => $text];
        }

        $speaker = null;
        $dialogueLines = [];
    }

    protected function resolveVoiceForSpeaker(string $cleanSpeaker, array &$voiceMap, ?Project $project): string
    {
        if (isset($voiceMap[$cleanSpeaker])) {
            return $voiceMap[$cleanSpeaker];
        }

        $voice = $this->voiceMapper->resolveFallbackVoice($cleanSpeaker);
        $voiceMap[$cleanSpeaker] = $voice;

        if ($project && $project->exists) {
            $this->voiceMapper->persistSpeakerToBible($project, $cleanSpeaker, $voice);
        }

        return $voice;
    }

    public function isCharacterLine(string $line): bool
    {
        $clean = trim($line);
        if ($clean === '' || mb_strlen($clean) > 45) {
            return false;
        }

        if (preg_match('/^(INT\.|EXT\.|INT\/EXT\.|I\/E\.|EST\.)\s+/iu', $clean)
            || preg_match('/^(CORTE A:|DISUELVE A:|FUNDIDO A NEGRO:|FADE OUT\.|FADE IN:|CUT TO:|DISSOLVE TO:)$/iu', $clean)) {
            return false;
        }

        $withoutParenthetical = trim((string) preg_replace('/\s*\([^\)]+\)$/u', '', $clean));
        if ($withoutParenthetical === '') {
            return false;
        }

        return preg_match('/^[A-ZÁÉÍÓÚÑ0-9\s\.\-\'\’]+$/u', $withoutParenthetical)
            && preg_match('/[A-ZÁÉÍÓÚÑ]/u', $withoutParenthetical)
            && ! str_ends_with($withoutParenthetical, '.')
            && ! str_ends_with($withoutParenthetical, ',')
            && ! str_ends_with($withoutParenthetical, ':');
    }

    public function buildCharacterVoiceMap(Collection|array $characters): array
    {
        return $this->voiceMapper->buildCharacterVoiceMap($characters);
    }

    public function resolveFallbackVoice(string $speaker): string
    {
        return $this->voiceMapper->resolveFallbackVoice($speaker);
    }

    public function inferIsFemale(Character $char): bool
    {
        return $this->voiceMapper->inferIsFemale($char);
    }
}
