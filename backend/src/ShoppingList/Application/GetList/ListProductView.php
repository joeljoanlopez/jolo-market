<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetList;

/**
 * The current catalog data of a product item. A product the catalog no longer knows has only
 * its id and is unavailable; every other field is null.
 */
final readonly class ListProductView
{
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $brand,
        public ?string $supermarketId,
        public ?int $priceCents,
        public ?string $imageUrl,
        public bool $available,
    ) {
    }
}
