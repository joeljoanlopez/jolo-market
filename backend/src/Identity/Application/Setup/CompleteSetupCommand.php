<?php

declare(strict_types=1);

namespace App\Identity\Application\Setup;

final readonly class CompleteSetupCommand
{
    public function __construct(
        #[\SensitiveParameter] public string $code,
        public string $username,
    ) {
    }
}
