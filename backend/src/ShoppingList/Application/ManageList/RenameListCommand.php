<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\ManageList;

final readonly class RenameListCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $name,
    ) {
    }
}
