<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

/**
 * One product flattened for display, available or not (PD-22). Units are "kg", "l" or "unit".
 * The section is the id of the store section its category maps to (PD-90, PD-91).
 */
final readonly class ProductView
{
    public function __construct(
        public string $productId,
        public string $name,
        public ?string $brand,
        public string $supermarketId,
        public string $supermarketName,
        public ?int $packageQuantityThousandths,
        public ?string $packageUnit,
        public int $priceCents,
        public ?int $unitPriceCents,
        public ?string $unitPriceUnit,
        public ?string $imageUrl,
        public bool $available,
        public string $section,
    ) {
    }
}
