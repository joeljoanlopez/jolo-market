<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

/**
 * Only the fields given change. Each one states the intent (PD-43): checked true sets checked,
 * never toggles. $position is the 0-based index to move the item to.
 */
final readonly class UpdateItemCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $itemId,
        public ?int $quantity = null,
        public ?bool $checked = null,
        public ?int $position = null,
    ) {
    }
}
