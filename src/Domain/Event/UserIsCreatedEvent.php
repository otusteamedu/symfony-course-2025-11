<?php

namespace App\Domain\Event;

readonly class UserIsCreatedEvent
{
    public function __construct(
        public int $id,
        public string $login,
    ) {
    }
}