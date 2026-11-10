<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Shared\Domain\Exception\Conflict;

final class LastActiveAdmin extends Conflict
{
    public static function create(): self
    {
        return new self('The last active admin must stay an active admin.');
    }
}
