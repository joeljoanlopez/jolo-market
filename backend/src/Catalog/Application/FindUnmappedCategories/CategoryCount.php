<?php

declare(strict_types=1);

namespace App\Catalog\Application\FindUnmappedCategories;

/**
 * A stored category path of one supermarket ("Despensa > Aceites") and how many products have it.
 */
final readonly class CategoryCount
{
    public function __construct(
        public string $supermarketId,
        public string $categoryPath,
        public int $productCount,
    ) {
    }
}
