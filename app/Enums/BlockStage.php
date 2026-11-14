<?php

declare(strict_types=1);

namespace App\Enums;

enum BlockStage: string
{
    case RAW = 'raw';
    case STRUCTURED = 'structured';
    case SCREENPLAY = 'screenplay';
    case SHOTS = 'shots';

    public function label(): string
    {
        return match ($this) {
            self::RAW => 'Bruto',
            self::STRUCTURED => 'Tratamiento Estructurado',
            self::SCREENPLAY => 'Guion Literario',
            self::SHOTS => 'Desglose de Planos',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::RAW => 1,
            self::STRUCTURED => 2,
            self::SCREENPLAY => 3,
            self::SHOTS => 4,
        };
    }

    public function isAdvancedThan(self $other): bool
    {
        return $this->order() > $other->order();
    }
}
