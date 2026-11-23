<?php

declare(strict_types=1);

namespace App\DTOs\Broadcast;

final readonly class BroadcastJobRequestDTO
{
    /**
     * @param  list<string|array<string, mixed>>  $sources
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
        );
    }
}
