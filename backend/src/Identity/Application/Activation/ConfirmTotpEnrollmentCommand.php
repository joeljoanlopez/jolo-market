<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

final readonly class ConfirmTotpEnrollmentCommand
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
        #[\SensitiveParameter] public string $code,
    ) {
    }
}
