<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\CheckListAccess;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;

/**
 * Query API for other contexts (E10.8): whether the actor may see the list, with the same rule
 * as GetList (owner or member, PD-63). Anyone else is told the list does not exist (PD-15).
 */
final readonly class CheckListAccessHandler
{
    public function __construct(private ListUpdater $updater)
    {
    }

    /**
     * @throws ShoppingListNotFound when the list is unknown or the actor is neither owner nor member
     */
    public function __invoke(CheckListAccessQuery $query): void
    {
        $list = $this->updater->load($query->listId);
        if (!$list->canBeAccessedBy(UserId::fromString($query->actorId))) {
            throw ShoppingListNotFound::withId($query->listId);
        }
    }
}
