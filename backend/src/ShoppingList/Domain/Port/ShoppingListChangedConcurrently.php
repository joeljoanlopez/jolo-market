<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Port;

use App\Shared\Domain\Exception\Conflict;

/**
 * Someone else saved the list first. The application retries the operation on a fresh copy
 * (PD-43), so a user sees it only when the list keeps changing under every retry.
 */
final class ShoppingListChangedConcurrently extends Conflict
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Shopping list "%s" changed while it was being saved. Try again.', $id));
    }
}
