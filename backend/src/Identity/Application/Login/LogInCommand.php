<?php

declare(strict_types=1);

namespace App\Identity\Application\Login;

final readonly class LogInCommand
{
    public function __construct(
        public string $username,
        #[\SensitiveParameter] public string $password,
    ) {
    }
}
