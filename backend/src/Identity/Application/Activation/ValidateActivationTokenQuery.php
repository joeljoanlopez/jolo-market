<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

final readonly class ValidateActivationTokenQuery
{
    public function __construct(#[\SensitiveParameter] public string $token)
    {
    }
}
