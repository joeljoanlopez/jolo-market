<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Shared\Domain\Exception\Conflict;

final class CannotManageYourself extends Conflict
{
    public static function create(): self
    {
        return new self('An admin cannot do this to their own account.');
    }
}
