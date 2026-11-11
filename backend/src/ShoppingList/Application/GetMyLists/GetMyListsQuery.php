<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetMyLists;

final readonly class GetMyListsQuery
{
    public function __construct(public string $actorId)
    {
    }
}
