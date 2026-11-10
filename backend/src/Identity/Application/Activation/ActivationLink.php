<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

final readonly class ActivationLink
{
    public function __construct(
        public string $username,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
