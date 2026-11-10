<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateUser;

use App\Shared\Domain\Exception\Conflict;

final class UsernameAlreadyTaken extends Conflict
{
    public static function named(string $username): self
    {
        return new self(\sprintf('The username "%s" is already taken.', $username));
    }
}
