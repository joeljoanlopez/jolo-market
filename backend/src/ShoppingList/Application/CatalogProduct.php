<?php

declare(strict_types=1);

namespace App\ShoppingList\Application;

/**
 * What a list needs to know about a Catalog product, with its current price and the id of the
 * store section its category maps to (PD-91).
 */
final readonly class CatalogProduct
{
    public function __construct(
        public string $productId,
        public string $name,
        public ?string $brand,
        public string $supermarketId,
        public string $supermarketName,
        public int $priceCents,
        public ?string $imageUrl,
        public bool $available,
        public string $section,
    ) {
    }
}
