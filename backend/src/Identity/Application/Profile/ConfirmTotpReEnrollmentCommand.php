<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

final readonly class ConfirmTotpReEnrollmentCommand
{
    public function __construct(
        public string $actorId,
        #[\SensitiveParameter] public string $code,
    ) {
    }
}
