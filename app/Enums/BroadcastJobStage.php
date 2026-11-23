<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastJobStage: string
{
    case RAW = 'RAW';
    case TREATMENT = 'TREATMENT';
    case SCREENPLAY = 'SCREENPLAY';
    case SHOTS = 'SHOTS';
    case RENDERING = 'RENDERING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';

    public function order(): int
    {
        return match ($this) {
            self::RAW => 1,
            self::TREATMENT => 2,
            self::SCREENPLAY => 3,
            self::SHOTS => 4,
            self::RENDERING => 5,
            self::COMPLETED => 6,
            self::FAILED => 99,
        };
    }

    public function nextEditorialStage(): ?self
    {
        return match ($this) {
            self::RAW => self::TREATMENT,
            self::TREATMENT => self::SCREENPLAY,
            self::SCREENPLAY => self::SHOTS,
            default => null,
        };
    }
}
