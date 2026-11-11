<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\Shared\Domain\Exception\Conflict;

final class OwnerCannotLeave extends Conflict
{
    public static function create(): self
    {
        return new self('The owner cannot leave their own list; they can delete it.');
    }
}
