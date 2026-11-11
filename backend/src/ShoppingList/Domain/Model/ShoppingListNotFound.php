<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\Shared\Domain\Exception\NotFound;

/**
 * Also thrown when the actor may not see the list, or may not do an owner-only action on it:
 * the API never reveals that a list exists (PD-15).
 */
final class ShoppingListNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Shopping list "%s" was not found.', $id));
    }
}
