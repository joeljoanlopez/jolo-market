<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api;

use App\Catalog\Application\GetProduct\ProductView;
use App\Catalog\Application\SearchProducts\ProductSearchResult;

/**
 * The JSON shape of a product, shared by search results and the product detail.
 */
final class ProductJson
{
    private const int THOUSANDTHS = 1000;

    /**
     * @return array<string, mixed>
     */
    public static function item(ProductSearchResult|ProductView $product): array
    {
        return [
            'id' => $product->productId,
            'name' => $product->name,
            'brand' => $product->brand,
            'supermarket' => ['id' => $product->supermarketId, 'name' => $product->supermarketName],
            'packaging' => null === $product->packageQuantityThousandths || null === $product->packageUnit
                ? null
                // Stored in thousandths so quantities stay integers; clients get the decimal value (1500 → 1.5).
                : ['quantity' => $product->packageQuantityThousandths / self::THOUSANDTHS, 'unit' => $product->packageUnit],
            'priceCents' => $product->priceCents,
            'unitPrice' => null === $product->unitPriceCents || null === $product->unitPriceUnit
                ? null
                : ['cents' => $product->unitPriceCents, 'unit' => $product->unitPriceUnit],
            'imageUrl' => $product->imageUrl,
        ];
    }
}
