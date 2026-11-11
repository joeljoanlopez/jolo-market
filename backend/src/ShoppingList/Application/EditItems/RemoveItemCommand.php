<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

final readonly class RemoveItemCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $itemId,
    ) {
    }
}
