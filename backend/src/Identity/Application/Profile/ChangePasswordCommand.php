<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public string $actorId,
        #[\SensitiveParameter] public string $currentPassword,
        #[\SensitiveParameter] public string $code,
        #[\SensitiveParameter] public string $newPassword,
    ) {
    }
}
