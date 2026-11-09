<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

final readonly class ResetUserCommand
{
    public function __construct(
        public string $actorId,
        public string $userId,
    ) {
    }
}
