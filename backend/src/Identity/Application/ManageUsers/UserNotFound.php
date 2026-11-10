<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Shared\Domain\Exception\NotFound;

final class UserNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('User "%s" was not found.', $id));
    }

    public static function named(string $username): self
    {
        return new self(\sprintf('User "%s" was not found.', $username));
    }
}
