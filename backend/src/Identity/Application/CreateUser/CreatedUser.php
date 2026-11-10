<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateUser;

/**
 * The plain activation token appears here only: it is never stored, so it cannot be shown again.
 */
final readonly class CreatedUser
{
    public function __construct(
        public string $userId,
        public string $username,
        #[\SensitiveParameter] public string $activationToken,
        public \DateTimeImmutable $activationExpiresAt,
    ) {
    }
}
