<?php

namespace App\Services\JgAgente\Fallbacks;

class ScriptDialogueRefiner
{
    /**
     * Refina texto en tercera persona a estilo directo Fountain.
     */
    public function refineDialogueToDirectSpeech(string $text, string $verb, ?string $particle = null): string
    {
        $res = trim($text);
        $res = preg_replace('/^que\s+/iu', '', $res);
        $res = preg_replace('/(?<=[.?!])\s*que\s+/iu', ' ', $res);

        $initialCore = $this->cleanPunctuation($res);
        if ($initialCore === '') {
            return str_contains($verb, 'pregunta') ? '¿...?' : (str_contains($verb, 'grita') ? '¡...!' : '...');
        }

        $canonicalParticle = match ($particle) {
            'qué' => 'qué',
            'quien', 'quién' => 'quién',
            'como', 'cómo' => 'cómo',
            'cuando', 'cuándo' => 'cuándo',
            'donde', 'dónde' => 'dónde',
            'por que', 'por qué' => 'por qué',
            default => null,
        };

        if ($canonicalParticle !== null && ! preg_match('/^\b'.preg_quote($canonicalParticle, '/').'\b/iu', $res)) {
            $res = $canonicalParticle.' '.$res;
        }

        $replacements = [
            '/\bse\s+ha\s+comportado\b/iu' => 'te has comportado',
            '/\bse\s+ha\s+portado\b/iu' => 'te has portado',
            '/\bse\s+comportó\b/iu' => 'te comportaste',
            '/\bvolverse\s+con\s+él\b/iu' => 'volverte conmigo',
            '/\birse\s+con\s+él\b/iu' => 'irte conmigo',
            '/\bvenirse\s+con\s+él\b/iu' => 'venirte conmigo',
            '/\bcon\s+él\b/iu' => 'conmigo',
            '/\bcon\s+ella\b/iu' => 'conmigo',
            '/\btiene\s+que\b/iu' => 'tienes que',
            '/\bdebe\b/iu' => 'debes',
            '/\bsu\s+castigo\b/iu' => 'tu castigo',
            '/\bsu\s+culpa\b/iu' => 'tu culpa',
            '/\bpara\s+recibir\s+su\b/iu' => 'para recibir tu',
            '/\bsus\s+actos\b/iu' => 'tus actos',
            '/\bsu\s+vida\b/iu' => 'tu vida',
            '/\bse\s+calle\b/iu' => 'te calles',
            '/\bno\s+se\s+meta\b/iu' => 'no te metas',
            '/\ble\s+deje\b/iu' => 'me dejes',
            '/\bestá\s+haciendo\b/iu' => 'estás haciendo',
        ];

        foreach ($replacements as $pat => $rep) {
            $res = preg_replace($pat, $rep, $res);
        }

        $res = preg_replace('/\s+y\s+que\s+/iu', '. ', $res);
        $res = preg_replace('/\s+y\s+(tienes|debes|ahora|no|vas)\b/iu', '. $1', $res);

        return $this->formatDialoguePunctuation($res, $verb, $particle);
    }

    /**
     * Aplica signos de puntuación e interrogación según verbo modal.
     */
    protected function formatDialoguePunctuation(string $res, string $verb, ?string $particle): string
    {
        $core = $this->cleanPunctuation($res);

        if (str_contains($verb, 'pregunta')) {
            if (preg_match('/^(?:quien|quién)\s+(.+)$/iu', $res, $mQ)) {
                return '¿Quién '.$mQ[1].'?';
            } elseif (preg_match('/^(?:por\s+qué|por\s+que|cómo|como|cuándo|cuando|dónde|donde|qué|que)\s+(.+)$/iu', $res, $mWh)) {
                $firstWord = strtolower(explode(' ', $res)[0]);
                $whWord = match ($firstWord) {
                    'por' => '¿Por qué '.preg_replace('/^por\s+qu[eé]\s+/iu', '', $res).'?',
                    'cómo', 'como' => '¿Cómo '.$mWh[1].'?',
                    'cuándo', 'cuando' => '¿Cuándo '.$mWh[1].'?',
                    'dónde', 'donde' => '¿Dónde '.$mWh[1].'?',
                    'qué', 'que' => '¿Qué '.$mWh[1].'?',
                    default => '¿'.$this->mbUcfirst($core).'?',
                };

                return $whWord;
            } elseif (preg_match('/^(?:está|esta|llega|vienen|es|pasa)\b/iu', $res) && ($particle === null || $particle === 'que' || $particle === 'quién' || $particle === 'quien')) {
                return '¿Quién '.$res.'?';
            }

            return $core === '' ? '¿...?' : '¿'.$this->mbUcfirst($core).'?';
        }

        if (str_contains($verb, 'grita')) {
            return $core === '' ? '¡...!' : '¡'.$this->mbUcfirst($core).'!';
        }

        $sentences = preg_split('/(?<=[.?!])\s+/u', $res);
        $sentences = array_filter(array_map(function ($s) {
            $sCore = $this->cleanPunctuation($s);

            return $sCore !== '' ? $this->mbUcfirst($sCore) : null;
        }, $sentences ?: []));

        if (empty($sentences)) {
            return $core !== '' ? $this->mbUcfirst($core).'.' : '...';
        }

        return $this->cleanPunctuation(implode(' ', $sentences)).'.';
    }

    /**
     * Resuelve acotaciones de modo para el diálogo.
     */
    public function resolveParenthetical(string $verb, string $verbMod): ?string
    {
        if (preg_match('/(?:al\s+o[ií]do)/iu', $verbMod)) {
            return 'susurrando al oído';
        }
        if (preg_match('/(?:en\s+voz\s+baja)/iu', $verbMod)) {
            return 'en voz baja';
        }
        if (preg_match('/(?:con\s+calma|tranquil[oa])/iu', $verbMod)) {
            return 'con calma';
        }
        if (preg_match('/(?:furios[oa]|enfadad[oa])/iu', $verbMod)) {
            return 'furioso';
        }
        if (str_contains($verb, 'susurra')) {
            return 'susurrando';
        }

        return null;
    }

    /**
     * Formatea segmento de acción narrativa en mayúsculas para personajes.
     *
     * @param  array<int, array<string, mixed>>  $characters
     */
    public function formatActionSegment(string $segment, array $characters, ?string &$activeSpeaker): string
    {
        $actionLine = $segment;
        foreach ($characters as $char) {
            if (is_array($char) && ! empty($char['name'])) {
                $cname = trim($char['name']);
                $pattern = '/\b'.preg_quote($cname, '/').'\b/iu';
                $actionLine = preg_replace($pattern, mb_strtoupper($cname, 'UTF-8'), $actionLine);
            }
        }

        $ignoreWords = ['se', 'el', 'la', 'los', 'las', 'un', 'una', 'al', 'en', 'por', 'tras', 'luego', 'mientras', 'cuando', 'como', 'donde'];
        if (preg_match('/\b([A-ZÁÉÍÓÚÑ][a-záéíóúñA-ZÁÉÍÓÚÑ]{1,15}(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñA-ZÁÉÍÓÚÑ]{1,15})?)\b/u', $actionLine, $mSpkAction)) {
            $firstWord = strtolower(explode(' ', $mSpkAction[1])[0]);
            if (! in_array($firstWord, $ignoreWords, true)) {
                $activeSpeaker = mb_strtoupper($mSpkAction[1], 'UTF-8');
            }
        }

        return $actionLine;
    }

    /**
     * Subdivide un párrafo en oraciones si contiene múltiples réplicas dialógicas.
     *
     * @return list<string>
     */
    public function splitParagraphIntoDialogueSegments(string $para): array
    {
        $para = trim($para);
        if ($para === '' || preg_match('/^([a-záéíóúñA-ZÁÉÍÓÚÑ\s]+?)(?:\s*\(([^)]+)\))?:\s*(.+)$/iu', $para) || preg_match('/^([a-záéíóúñA-ZÁÉÍÓÚÑ\s]+?)\s+(?:dice|responde|susurra|grita|pregunta|afirma)\s*:\s*["«]([^"»]+)["»]$/iu', $para)) {
            return [$para];
        }

        $speechVerbs = '(?:dice|dijo|decir|decirle|decirles|diciendo|diciéndole|diciéndoles|pregunta|preguntó|preguntar|preguntarle|preguntarles|preguntando|preguntándole|preguntándoles|responde|respondió|responder|responderle|responderles|respondiendo|respondiéndole|respondiéndoles|contesta|contestó|contestar|contestarle|contestarles|contestando|contestándole|contestándoles|susurra|susurró|susurrar|susurrarle|susurrando|susurrándole|grita|gritó|gritar|gritarle|gritando|gritándole|afirma|afirmó|afirmar|afirmando|exclama|exclamó|exclamar|exclamando|exclamándole|pide|pidió|pedir|pedirle|pidiendo|pidiéndole|pidiéndoles|murmura|murmuró|murmurar|murmurando|murmurándole|advierte|advirtió|advertir|advertirle|advirtiendo|advirtiéndole)';
        $sentences = preg_split('/(?<=[.?!])\s+/u', $para);
        $result = [];

        foreach ($sentences ?: [] as $sentence) {
            $s = trim($sentence);
            if ($s === '') {
                continue;
            }

            $subSegments = preg_split('/\s+y\s+(?=[A-ZÁÉÍÓÚÑ][\wáéíóúñÁÉÍÓÚÑ]+(?:\s+[A-ZÁÉÍÓÚÑ][\wáéíóúñÁÉÍÓÚÑ]+)?\s+(?:(?:\b(?:se|le|les|me|nos|te)\s+)?)\b'.$speechVerbs.'\b)/u', $s);
            foreach ($subSegments ?: [] as $sub) {
                $trimmed = trim($sub);
                if ($trimmed !== '') {
                    $result[] = $trimmed;
                }
            }
        }

        return ! empty($result) ? $result : [$para];
    }

    /**
     * Resuelve el hablante a partir de la frase previa.
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @return array{speaker: ?string, actionPart: ?string}
     */
    public function resolveSpeakerFromPrePart(string $prePart, array $characters, ?string $activeSpeaker = null): array
    {
        $speaker = null;
        $actionPart = null;
        $ignoreWords = ['se', 'el', 'la', 'los', 'las', 'un', 'una', 'al', 'en', 'por', 'tras', 'luego', 'mientras', 'cuando', 'como', 'donde', 'este', 'esta'];
        $candidates = [];

        foreach ($characters as $char) {
            $cName = trim((string) ($char['name'] ?? ''));
            if ($cName !== '' && preg_match_all('/\b'.preg_quote($cName, '/').'\b/iu', $prePart, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $before = mb_substr($prePart, 0, $offset, 'UTF-8');
                    $isObject = (bool) preg_match('/(?:^|\s)(?:a|al|hacia|contra|con)\s+$/iu', $before);
                    $candidates[] = ['name' => mb_strtoupper($cName, 'UTF-8'), 'offset' => $offset, 'is_object' => $isObject];
                }
            }
        }

        if (! empty($candidates)) {
            $subjects = array_filter($candidates, fn ($c) => ! $c['is_object']);
            $chosen = ! empty($subjects) ? $subjects : $candidates;
            usort($chosen, fn ($a, $b) => $a['offset'] <=> $b['offset']);
            $speaker = $chosen[0]['name'];
        }

        if (! $speaker && preg_match('/^([A-ZÁÉÍÓÚÑ][a-záéíóúñA-ZÁÉÍÓÚÑ]{1,15}(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñA-ZÁÉÍÓÚÑ]{1,15})?)\b/u', $prePart, $mLead)) {
            $candidate = trim($mLead[1]);
            if (! in_array(strtolower(explode(' ', $candidate)[0]), $ignoreWords, true)) {
                $speaker = mb_strtoupper($candidate, 'UTF-8');
            }
        }

        if (! $speaker && preg_match('/^(?:este|esta|él|ella)\b/iu', $prePart)) {
            $speaker = $activeSpeaker;
        }

        if (! empty($prePart)) {
            $stripped = $speaker ? trim(preg_replace('/\b'.preg_quote($speaker, '/').'\b/iu', '', $prePart)) : $prePart;
            if (trim($stripped, " ,.-;:\t\n\r") !== '') {
                $actionPart = $prePart;
            }
        }

        return ['speaker' => $speaker, 'actionPart' => $actionPart];
    }

    public function cleanPunctuation(string $str): string
    {
        return preg_replace('/^[\s.?!¿¡]+|[\s.?!¿¡]+$/u', '', $str) ?? trim($str);
    }

    public function mbUcfirst(string $str): string
    {
        if ($str === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($str, 1, null, 'UTF-8');
    }
}
