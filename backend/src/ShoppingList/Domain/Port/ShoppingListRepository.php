<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Port;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;

interface ShoppingListRepository
{
    /**
     * Inserts a new list, or stores the changes of a loaded one with its members and items.
     *
     * @throws ShoppingListChangedConcurrently when someone else saved the list after it was
     *                                         loaded (its stored version is no longer loadedVersion()), or deleted it
     */
    public function save(ShoppingList $list): void;

    /**
     * Deletes the list with its members and items (PD-44).
     */
    public function remove(ShoppingList $list): void;

    public function findById(ShoppingListId $id): ?ShoppingList;

    public function findByShareToken(ShareToken $shareToken): ?ShoppingList;

    /**
     * @return list<ShoppingList> the lists the user owns or is a member of, in no particular order
     */
    public function findByUser(UserId $userId): array;
}
