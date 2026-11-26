<?php

declare(strict_types=1);

namespace App\DTOs\Storyboard;

final readonly class VisualAestheticConfigDTO
{
    public const string MODE_STORYBOARD = 'storyboard_sketch';

    public const string MODE_FULL_BLEED = 'full_bleed_frame';

    public function __construct(
        public string $renderMode = self::MODE_STORYBOARD,
        public ?string $colorPalette = null,
        public ?string $lightingStyle = null,
        public ?string $cameraLens = null,
        public ?string $negativeDirectives = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $defaultRenderMode = self::MODE_STORYBOARD): self
    {
        $validModes = [self::MODE_STORYBOARD, self::MODE_FULL_BLEED];
        $fallbackMode = in_array($defaultRenderMode, $validModes, true) ? $defaultRenderMode : self::MODE_STORYBOARD;

        $rawMode = $data['render_mode'] ?? null;
        $renderMode = is_string($rawMode) && in_array($rawMode, $validModes, true)
            ? $rawMode
            : $fallbackMode;

        return new self(
            renderMode: $renderMode,
            colorPalette: self::normalizeNullableString($data['color_palette'] ?? null),
            lightingStyle: self::normalizeNullableString($data['lighting_style'] ?? null),
            cameraLens: self::normalizeNullableString($data['camera_lens'] ?? null),
            negativeDirectives: self::normalizeNullableString($data['negative_directives'] ?? null),
        );
    }

    /**
     * @return array{
     *     render_mode: string,
     *     color_palette: string|null,
     *     lighting_style: string|null,
     *     camera_lens: string|null,
     *     negative_directives: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'render_mode' => $this->renderMode,
            'color_palette' => $this->colorPalette,
            'lighting_style' => $this->lightingStyle,
            'camera_lens' => $this->cameraLens,
            'negative_directives' => $this->negativeDirectives,
        ];
    }

    public function isFullBleed(): bool
    {
        return $this->renderMode === self::MODE_FULL_BLEED;
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
