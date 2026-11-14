<?php

declare(strict_types=1);

namespace App\Services\Tts;

class TtsVoiceCatalog
{
    /**
     * @return array<int, array{id: string, name: string, gender: string, lang: string}>
     */
    public static function getAvailableVoices(?string $googleApiKey): array
    {
        if ($googleApiKey) {
            return [
                ['id' => 'Puck', 'name' => 'Puck (Masculino - Cálido y Narrativo)', 'gender' => 'male', 'lang' => 'es_ES'],
                ['id' => 'Charon', 'name' => 'Charon (Masculino - Profundo y Maduro)', 'gender' => 'male', 'lang' => 'es_ES'],
                ['id' => 'Fenrir', 'name' => 'Fenrir (Masculino - Enérgico e Intenso)', 'gender' => 'male', 'lang' => 'es_ES'],
                ['id' => 'Zephyr', 'name' => 'Zephyr (Masculino - Joven y Dinámico)', 'gender' => 'male', 'lang' => 'es_ES'],
                ['id' => 'Aoede', 'name' => 'Aoede (Femenino - Expresivo y Teatral)', 'gender' => 'female', 'lang' => 'es_ES'],
                ['id' => 'Kore', 'name' => 'Kore (Femenino - Sereno y Claro)', 'gender' => 'female', 'lang' => 'es_ES'],
                ['id' => 'Leda', 'name' => 'Leda (Femenino - Elegante y Cálido)', 'gender' => 'female', 'lang' => 'es_ES'],
            ];
        }

        return [
            ['id' => 'sharvard#M', 'name' => 'Castellano - Masculino (Sharvard M)', 'gender' => 'male', 'lang' => 'es_ES'],
            ['id' => 'sharvard#F', 'name' => 'Castellano - Femenino (Sharvard F)', 'gender' => 'female', 'lang' => 'es_ES'],
            ['id' => 'davefx', 'name' => 'Castellano - Masculino Profundo (Davefx)', 'gender' => 'male', 'lang' => 'es_ES'],
            ['id' => 'carlfm', 'name' => 'Castellano - Masculino Joven (Carlfm)', 'gender' => 'male', 'lang' => 'es_ES'],
            ['id' => 'mls_9972', 'name' => 'Castellano - Femenino Suave (MLS 9972)', 'gender' => 'female', 'lang' => 'es_ES'],
            ['id' => 'mls_10246', 'name' => 'Castellano - Masculino Sereno (MLS 10246)', 'gender' => 'male', 'lang' => 'es_ES'],
        ];
    }

    public static function resolveVoice(?string $voice, string $defaultVoice, ?string $googleApiKey): string
    {
        $v = $voice ?: $defaultVoice;
        if ($googleApiKey) {
            $legacyMap = [
                'sharvard#M' => 'Puck', 'sharvard#F' => 'Aoede', 'davefx' => 'Charon',
                'carlfm' => 'Zephyr', 'mls_9972' => 'Kore', 'mls_10246' => 'Fenrir',
            ];
            $valid = ['Puck', 'Charon', 'Fenrir', 'Zephyr', 'Aoede', 'Kore', 'Leda'];

            return $legacyMap[$v] ?? (in_array($v, $valid, true) ? $v : 'Puck');
        }

        return $v;
    }
}
