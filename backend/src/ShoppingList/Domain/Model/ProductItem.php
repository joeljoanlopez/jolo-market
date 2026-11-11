<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\ShoppingList\Domain\ValueObject\ProductId;

/**
 * A concrete product of a supermarket. Its name, price and supermarket are always read fresh
 * from the catalog, so they are not stored here.
 */
final readonly class ProductItem implements ItemContent
{
    public function __construct(public ProductId $productId)
    {
    }
}
