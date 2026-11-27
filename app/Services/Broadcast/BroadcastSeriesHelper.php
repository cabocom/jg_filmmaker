<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

final class BroadcastSeriesHelper
{
    /**
     * Devuelve el nombre de la serie asociado al formato del episodio.
     */
    public static function seriesNameForFormat(?string $format): string
    {
        return match (strtolower(trim((string) $format))) {
            'bio' => 'Breves Biografías IA',
            'pill', 'pildora' => 'Píldoras Tech IA',
            'news', 'noticia' => 'Noticias AI Today',
            'debate' => 'Debates AI Today',
            default => 'AI Today TV',
        };
    }

    /**
     * Construye el texto compacto de 2 líneas (\N) para el faldón inferior izquierdo ASS.
     */
    public static function buildLowerThirdText(
        ?string $format,
        string $title,
        ?string $sourceUrl = null,
        ?string $visualCredit = null,
    ): string {
        $series = self::seriesNameForFormat($format);
        $cleanTitle = trim($title);
        $line1 = $cleanTitle !== '' ? "{$series} · {$cleanTitle}" : $series;

        $metaParts = array_values(array_filter([
            $sourceUrl !== null ? trim($sourceUrl) : '',
            $visualCredit !== null ? trim($visualCredit) : '',
        ], static fn (string $part): bool => $part !== ''));

        if ($metaParts === []) {
            return $line1;
        }

        return $line1.'\N'.implode(' · ', $metaParts);
    }
}
