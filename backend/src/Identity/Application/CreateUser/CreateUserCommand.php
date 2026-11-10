<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateUser;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $actorId,
        public string $username,
        public string $role,
    ) {
    }
}
