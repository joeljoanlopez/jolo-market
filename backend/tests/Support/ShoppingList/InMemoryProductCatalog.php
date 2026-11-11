<?php

declare(strict_types=1);

namespace App\Tests\Support\ShoppingList;

use App\ShoppingList\Application\CatalogProduct;
use App\ShoppingList\Application\ProductCatalog;

final class InMemoryProductCatalog implements ProductCatalog
{
    /** @var array<string, CatalogProduct> */
    private array $products = [];

    public function add(
        string $productId,
        string $name,
        int $priceCents,
        string $supermarketId = 'mercadona',
        bool $available = true,
        ?string $brand = null,
        ?string $imageUrl = null,
    ): void {
        $supermarketName = match ($supermarketId) {
            'mercadona' => 'Mercadona',
            'aldi' => 'Aldi',
            'bonpreu' => 'Bonpreu i Esclat',
            default => ucfirst($supermarketId),
        };
        $this->products[$productId] = new CatalogProduct($productId, $name, $brand, $supermarketId, $supermarketName, $priceCents, $imageUrl, $available);
    }

    public function findByIds(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if (isset($this->products[$id->value()])) {
                $found[$id->value()] = $this->products[$id->value()];
            }
        }

        return $found;
    }
}
