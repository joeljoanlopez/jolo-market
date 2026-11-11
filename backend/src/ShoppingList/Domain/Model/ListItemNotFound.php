<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\Shared\Domain\Exception\NotFound;

final class ListItemNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('List item "%s" was not found.', $id));
    }
}
