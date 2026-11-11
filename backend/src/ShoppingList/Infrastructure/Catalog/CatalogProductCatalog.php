<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Catalog;

use App\Catalog\Application\GetProduct\GetProductsHandler;
use App\Catalog\Application\GetProduct\GetProductsQuery;
use App\ShoppingList\Application\CatalogProduct;
use App\ShoppingList\Application\ProductCatalog;
use App\ShoppingList\Domain\ValueObject\ProductId;

/**
 * Reads products through Catalog's application query API, never its domain (Deptrac).
 */
final readonly class CatalogProductCatalog implements ProductCatalog
{
    public function __construct(private GetProductsHandler $getProducts)
    {
    }

    public function findByIds(array $ids): array
    {
        $views = ($this->getProducts)(new GetProductsQuery(array_map(static fn (ProductId $id): string => $id->value(), $ids)));

        $products = [];
        foreach ($views as $id => $view) {
            $products[$id] = new CatalogProduct($view->productId, $view->name, $view->brand, $view->supermarketId, $view->supermarketName, $view->priceCents, $view->imageUrl, $view->available);
        }

        return $products;
    }
}
