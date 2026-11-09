<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

final readonly class ActivatedUser
{
    public function __construct(public string $userId)
    {
    }
}
