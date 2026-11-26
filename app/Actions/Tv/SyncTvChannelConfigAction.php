<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Models\TvChannelConfig;
use App\Services\Broadcast\TvMediaPathResolver;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

final class SyncTvChannelConfigAction
{
    public const array DEFAULT_VOICE_PROFILES = [
        'narrator' => 'Orus',
        'orus' => 'Orus',
        'puck' => 'Puck',
        'aoede' => 'Aoede',
        'debate_1' => 'Orus',
        'debate_2' => 'Puck',
        'debate_3' => 'Aoede',
    ];

    /** @var list<string> */
    private const array ALLOWED_FIELDS = [
        'name',
        'slug',
        'channel_slug',
        'tagline',
        'language',
        'timezone',
        'resolution_profile',
        'broadcast_profile',
        'tts_provider',
        'tts_voice',
        'is_active',
        'metadata',
        'videos_dir',
        'ads_dir',
    ];

    public function __construct(
        private readonly TvMediaPathResolver $pathResolver,
    ) {}

    public function getOrCreateConfig(string $channelSlug = 'ai-today'): TvChannelConfig
    {
        $config = TvChannelConfig::query()
            ->where('channel_slug', $channelSlug)
            ->orWhere('slug', $channelSlug)
            ->first();

        if (! $config instanceof TvChannelConfig) {
            $config = TvChannelConfig::create([
                'name' => 'AI Today TV',
                'slug' => $channelSlug,
                'channel_slug' => $channelSlug,
                'language' => 'es',
                'timezone' => 'Europe/Madrid',
                'resolution_profile' => '720p30',
                'broadcast_profile' => '720p30',
                'tts_provider' => 'gemini',
                'tts_voice' => 'Kore',
                'voice_profiles_json' => self::DEFAULT_VOICE_PROFILES,
                'youtube_connected' => false,
                'videos_dir' => 'ai-today/videos',
                'ads_dir' => 'ai-today/ads',
                'is_active' => true,
            ]);
        }

        $updates = [];
        if (empty($config->voice_profiles_json)) {
            $updates['voice_profiles_json'] = self::DEFAULT_VOICE_PROFILES;
        }
        if (empty($config->broadcast_profile)) {
            $updates['broadcast_profile'] = $config->resolution_profile ?: '720p30';
        }
        if (empty($config->videos_dir)) {
            $updates['videos_dir'] = 'ai-today/videos';
        }
        if (empty($config->ads_dir)) {
            $updates['ads_dir'] = 'ai-today/ads';
        }
        if ($updates !== []) {
            $config->update($updates);
            $config->refresh();
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveConfig(array $data, ?TvChannelConfig $config = null): TvChannelConfig
    {
        $config ??= $this->getOrCreateConfig((string) ($data['channel_slug'] ?? $data['slug'] ?? 'ai-today'));
        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));

        if (isset($filtered['resolution_profile']) && ! isset($filtered['broadcast_profile'])) {
            $filtered['broadcast_profile'] = $filtered['resolution_profile'];
        }
        if (isset($filtered['broadcast_profile']) && ! isset($filtered['resolution_profile'])) {
            $filtered['resolution_profile'] = $filtered['broadcast_profile'];
        }

        if ($filtered !== []) {
            $config->update($filtered);
        }

        return $config->refresh();
    }

    public function saveMediaStorageConfig(
        string $channelSlug,
        string $broadcastProfile,
        string $videosDir,
        string $adsDir
    ): TvChannelConfig {
        if (! in_array($broadcastProfile, ['720p30', '1080p30'], true)) {
            throw new InvalidArgumentException("Perfil de emisión inválido: [{$broadcastProfile}]. Permitidos: 720p30, 1080p30.");
        }

        $safeVideosDir = $this->pathResolver->normalizeRelativePath($videosDir, requireMp4Extension: false);
        $safeAdsDir = $this->pathResolver->normalizeRelativePath($adsDir, requireMp4Extension: false);

        $config = $this->getOrCreateConfig($channelSlug);
        $config->update([
            'broadcast_profile' => $broadcastProfile,
            'resolution_profile' => $broadcastProfile,
            'videos_dir' => $safeVideosDir,
            'ads_dir' => $safeAdsDir,
        ]);

        return $config->fresh() ?? $config;
    }

    public function saveYoutubeCredentials(
        string $channelSlug,
        ?string $channelId,
        ?string $oauthJson,
        ?string $streamKey
    ): TvChannelConfig {
        $config = $this->getOrCreateConfig($channelSlug);
        $payload = [];

        $trimmedKey = trim((string) $streamKey);
        $trimmedOauth = trim((string) $oauthJson);

        if ($channelId !== null) {
            $payload['youtube_channel_id'] = trim($channelId);
        }
        if ($trimmedOauth !== '') {
            $payload['youtube_oauth_encrypted'] = Crypt::encryptString($trimmedOauth);
        }
        if ($trimmedKey !== '') {
            $payload['youtube_stream_key_encrypted'] = Crypt::encryptString($trimmedKey);
            $payload['stream_key_fingerprint'] = strlen($trimmedKey) > 4 ? '...'.substr($trimmedKey, -4) : '****';
        }

        if ($payload !== []) {
            $config->update($payload);
            $config->refresh();
        }

        $isConnected = trim((string) $config->youtube_channel_id) !== '' && ! empty($config->youtube_stream_key_encrypted);
        $config->update(['youtube_connected' => $isConnected]);

        return $config->fresh() ?? $config;
    }

    /**
     * @param  array<string, mixed>  $profiles
     */
    public function saveVoiceProfiles(string $channelSlug, array $profiles): TvChannelConfig
    {
        $config = $this->getOrCreateConfig($channelSlug);
        $config->update(['voice_profiles_json' => $profiles]);

        return $config->fresh() ?? $config;
    }
}
