<?php

declare(strict_types=1);

namespace App\Identity\Application\Login;

final readonly class FindSessionUserQuery
{
    public function __construct(public string $userId)
    {
    }
}
