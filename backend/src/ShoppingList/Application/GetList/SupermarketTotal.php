<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetList;

/**
 * Estimated cost at one supermarket (PD-45): price × quantity of its available product items,
 * checked or not. $itemCount is the number of those items.
 */
final readonly class SupermarketTotal
{
    public function __construct(
        public string $supermarketId,
        public string $supermarketName,
        public int $totalCents,
        public int $itemCount,
    ) {
    }
}
