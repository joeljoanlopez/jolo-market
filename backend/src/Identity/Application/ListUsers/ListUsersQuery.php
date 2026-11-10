<?php

declare(strict_types=1);

namespace App\Identity\Application\ListUsers;

final readonly class ListUsersQuery
{
    public function __construct(public string $actorId)
    {
    }
}
