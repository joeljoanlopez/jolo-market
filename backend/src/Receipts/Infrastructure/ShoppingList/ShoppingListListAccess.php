<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\ShoppingList;

use App\Receipts\Application\ListAccess;
use App\Receipts\Application\ReceiptListNotFound;
use App\Receipts\Domain\ValueObject\ListId;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\CheckListAccess\CheckListAccessHandler;
use App\ShoppingList\Application\CheckListAccess\CheckListAccessQuery;

/**
 * Asks ShoppingList's application query API (E10.8), never its domain (Deptrac). Its not found is
 * the shopping list's domain exception, so it is caught by its parent, the shared NotFound.
 */
final readonly class ShoppingListListAccess implements ListAccess
{
    public function __construct(private CheckListAccessHandler $checkListAccess)
    {
    }

    public function assertCanAccess(UserId $actorId, ListId $listId): void
    {
        try {
            ($this->checkListAccess)(new CheckListAccessQuery($actorId->value(), $listId->value()));
        } catch (NotFound) {
            throw ReceiptListNotFound::withId($listId->value());
        }
    }
}
