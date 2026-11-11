<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Domain\Model\ListItemNotFound;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;
use App\ShoppingList\Domain\ValueObject\ListItemId;

/**
 * Parses an item id from a request. A malformed id is an unknown item, but only for someone who
 * can see the list; anyone else gets the list's 404 first.
 */
final class ItemReference
{
    public static function in(ShoppingList $list, UserId $actor, string $itemId): ListItemId
    {
        if (!$list->canBeAccessedBy($actor)) {
            throw ShoppingListNotFound::withId($list->id()->value());
        }

        try {
            return ListItemId::fromString($itemId);
        } catch (InvalidUuid) {
            throw ListItemNotFound::withId($itemId);
        }
    }
}
