<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Models\TvChannelConfig;

final readonly class SyncTvChannelConfigAction
{
    /**
     * @var list<string>
     */
    private const array UPDATABLE_FIELDS = [
        'name',
        'slug',
        'tagline',
        'language',
        'timezone',
        'resolution_profile',
        'tts_provider',
        'tts_voice',
        'is_active',
        'metadata',
    ];

    /**
     * Return the active channel configuration or create the default one.
     */
    public function getOrCreateConfig(): TvChannelConfig
    {
        $existing = TvChannelConfig::query()
            ->where('is_active', true)
            ->first()
            ?? TvChannelConfig::query()->first();

        if ($existing !== null) {
            return $existing;
        }

        return TvChannelConfig::create([
            'name' => 'JG TV',
            'slug' => 'jg-tv',
            'tagline' => 'Canal de televisión generativa IA',
            'language' => 'es',
            'timezone' => 'Europe/Madrid',
            'resolution_profile' => '1080p30',
            'tts_provider' => 'edge',
            'tts_voice' => 'es-ES-AlvaroNeural',
            'is_active' => true,
            'metadata' => [
                'publish_to_podcast' => true,
                'generate_short' => true,
            ],
        ]);
    }

    /**
     * Persist validated channel configuration fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveConfig(array $data, ?TvChannelConfig $config = null): TvChannelConfig
    {
        $target = $config ?? $this->getOrCreateConfig();
        $filtered = array_intersect_key($data, array_flip(self::UPDATABLE_FIELDS));

        if ($filtered !== []) {
            $target->update($filtered);
        }

        return $target->refresh();
    }
}
