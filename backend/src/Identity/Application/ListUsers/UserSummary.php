<?php

declare(strict_types=1);

namespace App\Identity\Application\ListUsers;

final readonly class UserSummary
{
    /**
     * @param ?\DateTimeImmutable $activationExpiresAt set only while the user has an activation link
     */
    public function __construct(
        public string $id,
        public string $username,
        public string $role,
        public string $status,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $activationExpiresAt,
    ) {
    }
}
