<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectCategory: string
{
    case PERSONAL = 'personal';
    case AI_VIDEO = 'ai_video';

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Proyectos personales',
            self::AI_VIDEO => 'Videos IA',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::PERSONAL => 'Personal',
            self::AI_VIDEO => 'Vídeo IA',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return ['personal', 'ai_video'];
    }

    public static function fromNullable(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::PERSONAL;
    }
}
