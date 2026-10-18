<?php

namespace App\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'Has alcanzado tu cupo diario de tokens de IA. El cupo se renovará automáticamente a las 00:00.',
        private readonly int $requiredTokens = 0,
        private readonly int $availableTokens = 0,
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRequiredTokens(): int
    {
        return $this->requiredTokens;
    }

    public function getAvailableTokens(): int
    {
        return $this->availableTokens;
    }
}
