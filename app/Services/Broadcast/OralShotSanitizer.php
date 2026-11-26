<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Models\Shot;
use Illuminate\Support\Collection;

final readonly class OralShotSanitizer
{
    public const int MIN_WORDS = 30;

    public const int MAX_WORDS = 60;

    public const float WORDS_PER_SECOND = 2.5;

    /**
     * Elimina elementos markdown (encabezados, negritas, cursivas, backticks, enlaces, viñetas, bloques de código)
     * y normaliza los espacios en blanco.
     */
    public function stripMarkdown(string $text): string
    {
        // Bloques de código multilínea ```...```
        $clean = (string) preg_replace('/```[\s\S]*?```/u', ' ', $text);

        // Enlaces [label](url) -> label
        $clean = (string) preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $clean);

        // Imágenes ![alt](url) -> eliminar
        $clean = (string) preg_replace('/!\[([^\]]*)\]\([^)]+\)/u', '', $clean);

        // Encabezados (#, ##, etc.) al inicio de línea o en texto inline
        $clean = (string) preg_replace('/#{1,6}\s*/u', '', $clean);
        $clean = str_replace('#', '', $clean);

        // Viñetas (- o *) al inicio de línea
        $clean = (string) preg_replace('/^\s*[\*\-+]\s+/mu', '', $clean);

        // Negritas y cursivas (**, __, *, _)
        $clean = (string) preg_replace('/(\*\*|__)(.*?)\1/u', '$2', $clean);
        $clean = (string) preg_replace('/(\*|_)(.*?)\1/u', '$2', $clean);
        $clean = str_replace(['**', '__'], '', $clean);

        // Bloques de código inline `...` y backticks sueltos
        $clean = (string) preg_replace('/`([^`]+)`/u', '$1', $clean);
        $clean = str_replace('`', '', $clean);

        // Citas (>)
        $clean = (string) preg_replace('/^\s*>\s+/mu', '', $clean);

        // Corchetes residuales
        $clean = str_replace(['[', ']'], '', $clean);

        // Normalización de espacios
        return trim((string) preg_replace('/\s+/u', ' ', $clean));
    }

    /**
     * Recorta suavemente el texto a un máximo de 60 palabras, priorizando límites de puntuación
     * entre MIN_WORDS y MAX_WORDS o añadiendo punto final si trunca.
     */
    public function clampOralText(string $cleanText): string
    {
        $cleanText = trim($cleanText);
        if ($cleanText === '') {
            return '';
        }

        /** @var list<string> $words */
        $words = preg_split('/\s+/u', $cleanText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= self::MAX_WORDS) {
            return $cleanText;
        }

        $sliced = array_slice($words, 0, self::MAX_WORDS);

        for ($i = count($sliced) - 1; $i >= self::MIN_WORDS - 1; $i--) {
            if (preg_match('/[.!?]$/u', $sliced[$i])) {
                return implode(' ', array_slice($sliced, 0, $i + 1));
            }
        }

        $candidate = implode(' ', $sliced);
        $trimmed = rtrim($candidate, " \t\n\r\0\x0B,;:-\"'-");

        return rtrim($trimmed, '.').'.';
    }

    /**
     * Estima la duración en segundos en un rango de 12 a 25 segundos según la cadencia TTS.
     */
    public function estimateSeconds(string $cleanText): int
    {
        $cleanText = trim($cleanText);
        if ($cleanText === '') {
            return 12;
        }

        $words = preg_split('/\s+/u', $cleanText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);
        if ($wordCount === 0) {
            return 12;
        }

        return max(12, min(25, (int) round($wordCount / self::WORDS_PER_SECOND)));
    }

    /**
     * Sanitiza la descripción de los planos de la colección y construye el payload estructurado.
     *
     * @param  Collection<int, Shot>  $shots
     * @return list<array{shot_id: string, shot_number: int, shot_type: string|null, oral_text: string, word_count: int, estimated_seconds: int}>
     */
    public function sanitizeAndFormatShots(Collection $shots): array
    {
        $formatted = [];

        foreach ($shots as $shot) {
            $rawDescription = (string) ($shot->description ?? '');
            $clean = $this->clampOralText($this->stripMarkdown($rawDescription));

            if ($shot->description !== $clean) {
                $shot->update(['description' => $clean]);
            }

            $words = count(preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $seconds = $this->estimateSeconds($clean);

            $formatted[] = [
                'shot_id' => (string) $shot->id,
                'shot_number' => (int) $shot->shot_number,
                'shot_type' => $shot->shot_type,
                'oral_text' => $clean,
                'word_count' => $words,
                'estimated_seconds' => $seconds,
            ];
        }

        return $formatted;
    }

    /**
     * Elimina atribuciones de licencia Wikipedia / CC BY-SA del texto locutado
     * y devuelve el texto limpio junto al crédito visual normalizado.
     *
     * @return array{clean_text: string, extracted_credit: ?string}
     */
    public function stripLicenseAttribution(string $text): array
    {
        $patterns = [
            '/(?:^|\s)[\(\[]?\s*(?:Fuente|Source|Texto\s+de|Basado\s+en)\s*:\s*Wikipedia\b[^\n]*?(?:CC[\s\-]*BY[\s\-]*SA(?:\s*\d+(?:\.\d+)?)?|Creative\s+Commons[^\n.!?\)\]]*)[\s\.\)\]]*/iu',
            '/(?:^|\s)[\(\[]?\s*(?:Contenido|Texto|Artículo|Información)?\s*(?:de\s+Wikipedia\s+)?bajo\s+licencia\s+(?:CC[\s\-]*BY[\s\-]*SA(?:\s*\d+(?:\.\d+)?)?|Creative\s+Commons[^\n.!?\)\]]*)[\s\.\)\]]*/iu',
            '/(?:^|\s)[\(\[]?\s*Wikipedia\s*[·|,;\-–:]\s*CC[\s\-]*BY[\s\-]*SA(?:\s*\d+(?:\.\d+)?)?[\s\.\)\]]*/iu',
            '/(?:^|\s)[\(\[]?\s*(?:Fuente|Source|Texto\s+de|Basado\s+en)\s*:\s*Wikipedia\b[^.!?\n]*[\.\)\]]*/iu',
        ];

        $clean = $text;
        $extractedCredit = null;

        foreach ($patterns as $pattern) {
            $replaced = preg_replace($pattern, ' ', $clean, -1, $count);
            if ($replaced !== null && $count > 0) {
                $clean = $replaced;
                $extractedCredit = 'Fuente: Wikipedia · CC BY-SA 4.0';
            }
        }

        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return [
            'clean_text' => $clean,
            'extracted_credit' => $extractedCredit,
        ];
    }
}
