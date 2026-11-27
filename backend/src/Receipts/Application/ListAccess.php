<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Receipts\Domain\ValueObject\ListId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Port to the ShoppingList context (E10.8): receipts belong to a list, and only its owner and
 * members may see them (PD-63).
 */
interface ListAccess
{
    /**
     * @throws ReceiptListNotFound when the list is unknown or the actor is neither owner nor member
     */
    public function assertCanAccess(UserId $actorId, ListId $listId): void;
}
