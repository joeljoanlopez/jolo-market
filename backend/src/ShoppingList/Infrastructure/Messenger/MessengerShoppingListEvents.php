<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Messenger;

use App\ShoppingList\Application\ManageList\ShoppingListDeleted;
use App\ShoppingList\Application\ShoppingListEvents;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Publishes the events on the message bus; config/packages/messenger.yaml routes them to the
 * transport of whoever handles them, so this context knows nothing about its listeners (PD-44).
 */
final readonly class MessengerShoppingListEvents implements ShoppingListEvents
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function listDeleted(ShoppingListDeleted $event): void
    {
        $this->bus->dispatch($event);
    }
}
