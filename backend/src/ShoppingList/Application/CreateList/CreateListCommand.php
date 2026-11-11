<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\CreateList;

final readonly class CreateListCommand
{
    public function __construct(
        public string $actorId,
        public string $name,
    ) {
    }
}
