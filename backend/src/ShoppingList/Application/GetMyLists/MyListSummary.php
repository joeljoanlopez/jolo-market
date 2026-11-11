<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetMyLists;

/**
 * $role is "owner" or "member".
 */
final readonly class MyListSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public string $ownerUsername,
        public int $itemCount,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
