<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Messenger;

use App\Receipts\Application\RemoveListReceipts\RemoveListReceiptsCommand;
use App\Receipts\Application\RemoveListReceipts\RemoveListReceiptsHandler;
use App\ShoppingList\Application\ManageList\ShoppingListDeleted;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PD-63: run by the worker after a list is deleted, to remove its receipts and their photos.
 */
#[AsMessageHandler]
final readonly class ShoppingListDeletedMessageHandler
{
    public function __construct(private RemoveListReceiptsHandler $removeListReceipts)
    {
    }

    public function __invoke(ShoppingListDeleted $event): void
    {
        ($this->removeListReceipts)(new RemoveListReceiptsCommand($event->listId));
    }
}
