<?php

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final readonly class CommunicationChannel
{
    private const EMAIL = 'email';
    private const PHONE = 'phone';
    private const ALLOWED_VALUES = [self::PHONE, self::EMAIL];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::ALLOWED_VALUES, true)) {
            throw new InvalidArgumentException('Invalid communication channel value');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}