<?php

declare(strict_types=1);

namespace App\ShoppingList\Application;

use App\ShoppingList\Domain\ValueObject\ProductId;

/**
 * Port to the Catalog context (E4.9): fresh product data for list items and totals.
 */
interface ProductCatalog
{
    /**
     * @param list<ProductId> $ids
     *
     * @return array<string, CatalogProduct> by product id, available or not (PD-22); unknown ids are left out
     */
    public function findByIds(array $ids): array;
}
