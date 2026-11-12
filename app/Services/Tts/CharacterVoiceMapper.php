<?php

declare(strict_types=1);

namespace App\Services\Tts;

use App\Models\Character;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CharacterVoiceMapper
{
    public function __construct(
        protected bool $hasGoogleApiKey
    ) {}

    public function buildCharacterVoiceMap(Collection|array $characters): array
    {
        $map = [];
        $femaleList = $this->hasGoogleApiKey ? ['Aoede', 'Kore', 'Leda'] : ['sharvard#F', 'mls_9972'];
        $maleList = $this->hasGoogleApiKey ? ['Puck', 'Charon', 'Fenrir', 'Zephyr'] : ['sharvard#M', 'davefx', 'carlfm', 'mls_10246'];
        $femaleIdx = 0;
        $maleIdx = 0;

        foreach ($characters as $char) {
            $nameKey = rtrim(strtoupper(trim((string) $char->name)), '.');
            $customAttrs = is_array($char->custom_attributes) ? $char->custom_attributes : [];
            $assignedVoice = $customAttrs['voice_id'] ?? null;

            if ($assignedVoice) {
                $map[$nameKey] = (string) $assignedVoice;

                continue;
            }

            $isFemale = $this->inferIsFemale($char);
            $chosenVoice = $isFemale ? $femaleList[$femaleIdx++ % count($femaleList)] : $maleList[$maleIdx++ % count($maleList)];
            $map[$nameKey] = $chosenVoice;

            $customAttrs['voice_id'] = $chosenVoice;
            $char->custom_attributes = $customAttrs;
            if ($char->exists) {
                $char->saveQuietly();
            }
        }

        return $map;
    }

    public function resolveFallbackVoice(string $speaker): string
    {
        $clean = rtrim(strtoupper(trim($speaker)), '.');
        $femaleKeywords = ['MUJER', 'CHICA', 'MADRE', 'LUCÍA', 'ANA', 'EDURNE', 'MARIA', 'MARÍA', 'NEKANE', 'VOZ IA', 'IA'];
        $isFemale = collect($femaleKeywords)->contains(fn ($kw) => str_contains($clean, $kw));

        if ($this->hasGoogleApiKey) {
            return $isFemale ? 'Aoede' : 'Charon';
        }

        return $isFemale ? 'sharvard#F' : 'sharvard#M';
    }

    public function persistSpeakerToBible(Project $project, string $cleanSpeaker, string $voice): void
    {
        try {
            $existing = $project->characters()->whereRaw('UPPER(TRIM(name)) = ?', [$cleanSpeaker])->first();
            if ($existing) {
                $attrs = is_array($existing->custom_attributes) ? $existing->custom_attributes : [];
                $attrs['voice_id'] = $voice;
                $existing->custom_attributes = $attrs;
                $existing->saveQuietly();
            } else {
                $project->characters()->create([
                    'name' => ucwords(strtolower($cleanSpeaker)),
                    'custom_attributes' => ['voice_id' => $voice],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("No se pudo persistir personaje {$cleanSpeaker} en la Biblia: ".$e->getMessage());
        }
    }

    public function inferIsFemale(Character $char): bool
    {
        $customAttrs = is_array($char->custom_attributes) ? $char->custom_attributes : [];
        if (! empty($customAttrs['gender'])) {
            return in_array(mb_strtolower((string) $customAttrs['gender']), ['female', 'femenino', 'mujer'], true);
        }

        $text = mb_strtolower($char->name.' '.($char->bio ?? '').' '.($char->psychology ?? ''));
        if (preg_match('/\b(mujer|femenina|femenino|chica|actriz|hija|madre|hermana|abuela|señora|camarera|doctora|muchacha|chavala|locutora|protagonista femenina)\b/u', $text)) {
            return true;
        }

        $name = rtrim(mb_strtolower(trim($char->name)), '.');
        $femaleNames = [
            'ana', 'lucía', 'lucia', 'maría', 'maria', 'carmen', 'elena', 'laura', 'sofía', 'sofia',
            'clara', 'marta', 'sara', 'irene', 'paula', 'edurne', 'nekane', 'ainhoa', 'itziar',
            'arantxa', 'maite', 'leire', 'nerea', 'begoña', 'idoia', 'amaia', 'miren', 'almudena',
            'inés', 'ines', 'rocío', 'rocio', 'raquel', 'beatriz', 'isabel', 'teresa', 'eva',
            'silvia', 'cristina', 'patricia', 'mónica', 'monica', 'alicia', 'rosa', 'pilar',
            'mercedes', 'lourdes', 'victoria', 'blanca', 'esther', 'ester', 'julia', 'natalia',
            'lorena', 'alba', 'claudia', 'andrea', 'celia', 'marina', 'triana', 'voz ia', 'ia',
        ];

        return in_array($name, $femaleNames, true)
            || (preg_match('/(a|ne|z|is|ina|esa|isa|triz)$/u', $name) && ! in_array($name, ['borja', 'luca', 'joshua', 'andrea'], true));
    }
}
