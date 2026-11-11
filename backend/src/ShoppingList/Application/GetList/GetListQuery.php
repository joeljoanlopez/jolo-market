<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetList;

final readonly class GetListQuery
{
    public function __construct(
        public string $actorId,
        public string $listId,
    ) {
    }
}
