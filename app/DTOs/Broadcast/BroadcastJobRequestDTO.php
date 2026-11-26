<?php

declare(strict_types=1);

namespace App\DTOs\Broadcast;

final readonly class BroadcastJobRequestDTO
{
    /**
     * @param  list<string|array<string, mixed>>  $sources
     * @param  list<array<string, mixed>>  $castProfiles
     * @param  array<string, mixed>|null  $locationProfile
     */
    public function __construct(
        public string $channelId,
        public string $format,
        public string $title,
        public string $rawText,
        public string $language = 'es',
        public array $sources = [],
        public string $visualMode = 'storyboard',
        public ?string $callbackUrl = null,
        public string $resolutionProfile = '1080p30',
        public ?string $outputRelativePath = null,
        public array $castProfiles = [],
        public ?array $locationProfile = null,
        public ?string $ttsVoice = null,
        public ?string $ttsProvider = null,
        public ?string $visualCredit = null,
        public ?bool $publishToPodcast = null,
        public ?bool $generateShort = null,
        public ?string $projectId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channelId: (string) ($data['channel_id'] ?? $data['channel_slug'] ?? ''),
            format: (string) ($data['format'] ?? 'pill'),
            title: (string) ($data['title'] ?? ''),
            rawText: (string) ($data['raw_text'] ?? ''),
            language: (string) ($data['language'] ?? 'es'),
            sources: (array) ($data['sources'] ?? []),
            visualMode: (string) ($data['visual_mode'] ?? 'storyboard'),
            callbackUrl: isset($data['callback_url']) && is_string($data['callback_url']) ? $data['callback_url'] : null,
            resolutionProfile: (string) ($data['resolution_profile'] ?? '1080p30'),
            outputRelativePath: isset($data['output_relative_path']) && is_string($data['output_relative_path']) && trim($data['output_relative_path']) !== '' ? trim($data['output_relative_path']) : null,
            castProfiles: isset($data['cast_profiles']) && is_array($data['cast_profiles'])
                ? array_values(array_filter($data['cast_profiles'], 'is_array'))
                : [],
            locationProfile: isset($data['location_profile']) && is_array($data['location_profile'])
                ? $data['location_profile']
                : null,
            ttsVoice: isset($data['tts_voice']) && is_string($data['tts_voice']) && trim($data['tts_voice']) !== ''
                ? trim($data['tts_voice'])
                : null,
            ttsProvider: isset($data['tts_provider']) && is_string($data['tts_provider']) && trim($data['tts_provider']) !== ''
                ? trim($data['tts_provider'])
                : null,
            visualCredit: isset($data['visual_credit']) && is_string($data['visual_credit']) && trim($data['visual_credit']) !== ''
                ? trim($data['visual_credit'])
                : null,
            publishToPodcast: array_key_exists('publish_to_podcast', $data) && $data['publish_to_podcast'] !== null
                ? (bool) $data['publish_to_podcast']
                : null,
            generateShort: array_key_exists('generate_short', $data) && $data['generate_short'] !== null
                ? (bool) $data['generate_short']
                : null,
            projectId: isset($data['project_id']) && is_string($data['project_id']) && trim($data['project_id']) !== ''
                ? trim($data['project_id'])
                : null,
        );
    }
}
