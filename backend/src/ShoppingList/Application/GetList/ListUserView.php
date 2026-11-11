<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetList;

final readonly class ListUserView
{
    public function __construct(
        public string $id,
        public string $username,
    ) {
    }
}
