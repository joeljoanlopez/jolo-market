<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\CheckListAccess;

final readonly class CheckListAccessQuery
{
    public function __construct(
        public string $actorId,
        public string $listId,
    ) {
    }
}
