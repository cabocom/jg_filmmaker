<?php

declare(strict_types=1);

namespace App\Services\Tv;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PillNarrativeRewriter
{
    private const string SYSTEM_PROMPT_ES = <<<'PROMPT'
Eres un guionista de píldoras divulgativas para televisión.
Tu misión es transformar un extracto enciclopédico en un guion breve, directo y cautivador listo para locución TTS.

REGLAS EDITORIALES ESTRICTAS:
1. Céntrate EXCLUSIVAMENTE en lo que hizo, qué problema resolvió, qué aportó y cuál es la huella o impacto que dejó (por qué merece esta píldora).
2. PROHIBIDO incluir datos biográficos triviales: NO menciones fechas ni lugares de nacimiento o muerte, nombres completos de pila/bautismo, padres, madres, cónyuges, hijos ni genealogía.
3. NO empieces con "X fue un... nacido en...". Empieza directamente con su logro, idea clave o impacto.
4. Devuelve ÚNICAMENTE prosa limpia para locutar en español, sin Markdown, sin títulos, sin viñetas ni prefijos tipo HOST: o LOCUTOR:.
5. Ajústate aproximadamente a %d palabras.
PROMPT;

    private const string SYSTEM_PROMPT_EN = <<<'PROMPT'
You are a TV educational micro-documentary scriptwriter.
Transform an encyclopedic extract into a crisp, engaging spoken script ready for TTS narration.

STRICT EDITORIAL RULES:
1. Focus EXCLUSIVELY on what they did, what problem they solved, their breakthrough, and the lasting impact or legacy they left behind.
2. FORBIDDEN trivial biographical details: DO NOT mention birth or death dates/places, full birth names, parents, mothers, fathers, spouses, or genealogy.
3. DO NOT open with "X was born in...". Open directly with their achievement, key idea, or impact.
4. Return ONLY clean spoken prose in English, no Markdown, no headings, no bullets, and no HOST:/NARRATOR: prefixes.
5. Target approximately %d words.
PROMPT;

    public function rewrite(string $topicTitle, string $rawExtract, string $language, int $targetWords): string
    {
        $cleanExtract = $this->stripBiographicalNoise($rawExtract);
        $llmText = $this->callLlm($topicTitle, $rawExtract, $language, $targetWords);

        if ($llmText !== null && $llmText !== '') {
            return $llmText;
        }

        return $cleanExtract !== '' ? $cleanExtract : $rawExtract;
    }

    /**
     * Limpia paréntesis con fechas vitales y oraciones puramente genealógicas como respaldo determinista.
     */
    public function stripBiographicalNoise(string $text): string
    {
        // Eliminar paréntesis biográficos típicos de Wikipedia con años de nacimiento/muerte o lugares
        $clean = (string) preg_replace('/\s*\([^()]*\b\d{4}\b[^()]*\)/u', '', $text);

        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $filtered = array_values(array_filter($sentences, static function (string $sentence): bool {
            $lower = mb_strtolower($sentence, 'UTF-8');

            return ! preg_match('/\b(naci[oó]\s+el|falleci[oó]\s+el|muri[oó]\s+el|hijo\s+de|hija\s+de|sus\s+padres|bautizado\s+como|su\s+padre\s+era|su\s+madre\s+era|was\s+born\s+on|died\s+on|son\s+of|daughter\s+of|his\s+parents|her\s+parents)\b/u', $lower);
        }));

        $result = $filtered !== [] ? implode(' ', $filtered) : $clean;

        return trim((string) preg_replace('/\s+/u', ' ', $result));
    }

    private function callLlm(string $topicTitle, string $rawExtract, string $language, int $targetWords): ?string
    {
        $apiKey = trim((string) config('services.llm.api_key', ''));
        if ($apiKey === '') {
            return null;
        }

        $provider = strtolower(trim((string) config('services.llm.provider', 'groq')));
        $model = (string) config('services.llm.model', 'openai/gpt-oss-120b');
        $timeout = (int) config('services.llm.timeout', 45);
        $endpoint = match ($provider) {
            'openai' => 'https://api.openai.com/v1/chat/completions',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            default => 'https://api.groq.com/openai/v1/chat/completions',
        };

        $isEnglish = strtolower(trim($language)) === 'en';
        $systemPrompt = sprintf($isEnglish ? self::SYSTEM_PROMPT_EN : self::SYSTEM_PROMPT_ES, $targetWords);
        $userPrompt = "Tema: {$topicTitle}\n\nExtracto fuente:\n".mb_substr($rawExtract, 0, 4500);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post($endpoint, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.4,
                ]);

            if (! $response->successful()) {
                Log::warning('PillNarrativeRewriter: LLM request failed', ['status' => $response->status()]);

                return null;
            }

            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));

            return $content !== '' ? trim((string) preg_replace('/\s+/u', ' ', $content)) : null;
        } catch (Throwable $e) {
            Log::warning('PillNarrativeRewriter: LLM exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
