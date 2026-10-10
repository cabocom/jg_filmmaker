<?php

namespace App\Services;

class ScreenplayFormatterService
{
    /**
     * Formatea texto de guión Fountain a bloques HTML con la tipografía y disposición espacial
     * canónica de Story Architect / Final Draft (personaje centrado, diálogo en columna acotada, acción completa).
     */
    public static function formatToHtml(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Normalizar saltos de línea
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = explode("\n", $text);

        $html = '';
        $inDialogueBlock = false;
        $previousLineWasEmpty = true;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                if ($inDialogueBlock) {
                    $html .= "</div>\n";
                    $inDialogueBlock = false;
                }
                $previousLineWasEmpty = true;

                continue;
            }

            // 1. Encabezado de escena (Slugline)
            if (self::isSlugline($line)) {
                if ($inDialogueBlock) {
                    $html .= "</div>\n";
                    $inDialogueBlock = false;
                }
                $html .= '<div class="screenplay-slugline font-screenplay font-bold uppercase tracking-wider text-slate-900 dark:text-amber-100 my-2.5 text-left">'.e($line)."</div>\n";
                $previousLineWasEmpty = false;

                continue;
            }

            // 2. Transición
            if (self::isTransition($line)) {
                if ($inDialogueBlock) {
                    $html .= "</div>\n";
                    $inDialogueBlock = false;
                }
                $html .= '<div class="screenplay-transition font-screenplay font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 my-2 text-right">'.e($line)."</div>\n";
                $previousLineWasEmpty = false;

                continue;
            }

            // 3. Personaje (Inicio de bloque de diálogo)
            // En Fountain, un personaje debe estar precedido por una línea en blanco o ser la primera línea
            if (self::isCharacterLine($line) && ($previousLineWasEmpty || ! $inDialogueBlock)) {
                if ($inDialogueBlock) {
                    $html .= "</div>\n";
                }
                $inDialogueBlock = true;
                $html .= '<div class="screenplay-dialogue-block my-2.5 font-screenplay">'."\n";
                $html .= '    <div class="screenplay-character text-center font-bold tracking-wider uppercase text-slate-900 dark:text-amber-200 text-xs sm:text-sm pt-1 pb-0.5 select-none">'.e($line)."</div>\n";
                $previousLineWasEmpty = false;

                continue;
            }

            // 4. Si estamos dentro de un bloque de diálogo:
            if ($inDialogueBlock) {
                if (self::isParenthetical($line)) {
                    $html .= '    <div class="screenplay-parenthetical text-center italic text-xs text-slate-500 dark:text-slate-400 py-0.5">'.e($line)."</div>\n";
                } else {
                    $html .= '    <div class="screenplay-dialogue max-w-md mx-auto text-left leading-relaxed px-4 sm:px-8 text-slate-800 dark:text-slate-200 pb-1">'.e($line)."</div>\n";
                }
                $previousLineWasEmpty = false;

                continue;
            }

            // 5. Acción descriptiva estándar (Story Architect: ancho completo, texto a la izquierda)
            $html .= '<div class="screenplay-action font-screenplay text-left leading-relaxed my-1.5 text-slate-800 dark:text-slate-200">'.e($line)."</div>\n";
            $previousLineWasEmpty = false;
        }

        if ($inDialogueBlock) {
            $html .= "</div>\n";
        }

        return $html;
    }

    protected static function isSlugline(string $line): bool
    {
        return (bool) preg_match('/^(INT\.|EXT\.|INT\/EXT\.|I\/E\.|EST\.)\s+/iu', $line);
    }

    protected static function isTransition(string $line): bool
    {
        return (bool) preg_match('/^(CORTE A:|DISUELVE A:|FUNDIDO A NEGRO:|FADE OUT\.|FADE IN:|CUT TO:|DISSOLVE TO:)$/iu', $line);
    }

    protected static function isParenthetical(string $line): bool
    {
        return str_starts_with($line, '(') && str_ends_with($line, ')');
    }

    protected static function isCharacterLine(string $line): bool
    {
        $clean = trim($line);
        if ($clean === '' || mb_strlen($clean) > 45) {
            return false;
        }

        if (self::isSlugline($clean) || self::isTransition($clean)) {
            return false;
        }

        // Remover acotación final si la hay ej: "JUAN (V.O.)" o "PEDRO (O.S.)"
        $withoutParenthetical = preg_replace('/\s*\([^\)]+\)$/u', '', $clean);
        $withoutParenthetical = trim($withoutParenthetical);

        if ($withoutParenthetical === '') {
            return false;
        }

        // Debe estar en mayúsculas y contener al menos una letra
        return preg_match('/^[A-ZÁÉÍÓÚÑ0-9\s\.\-\'\’]+$/u', $withoutParenthetical)
            && preg_match('/[A-ZÁÉÍÓÚÑ]/u', $withoutParenthetical)
            && ! str_ends_with($withoutParenthetical, '.')
            && ! str_ends_with($withoutParenthetical, ',')
            && ! str_ends_with($withoutParenthetical, ':');
    }
}
