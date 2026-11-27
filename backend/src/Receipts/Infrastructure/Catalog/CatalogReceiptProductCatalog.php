<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Catalog;

use App\Catalog\Application\GetProduct\GetProductsHandler;
use App\Catalog\Application\GetProduct\GetProductsQuery;
use App\Catalog\Application\SuggestProducts\SuggestProductsHandler;
use App\Catalog\Application\SuggestProducts\SuggestProductsQuery;
use App\Receipts\Application\ReceiptProduct;
use App\Receipts\Application\ReceiptProductCatalog;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\SupermarketId;

/**
 * Reads products through Catalog's application query APIs (E10.7, E4.14), never its domain (Deptrac).
 */
final readonly class CatalogReceiptProductCatalog implements ReceiptProductCatalog
{
    public function __construct(
        private SuggestProductsHandler $suggestProducts,
        private GetProductsHandler $getProducts,
    ) {
    }

    public function bestMatch(SupermarketId $supermarketId, LineText $text): ?ProductId
    {
        $suggestions = ($this->suggestProducts)(new SuggestProductsQuery($supermarketId->value(), $text->value(), 1));

        return [] === $suggestions ? null : ProductId::fromString($suggestions[0]->productId);
    }

    public function findByIds(array $ids): array
    {
        $views = ($this->getProducts)(new GetProductsQuery(array_map(static fn (ProductId $id): string => $id->value(), $ids)));

        $products = [];
        foreach ($views as $id => $view) {
            $products[$id] = new ReceiptProduct($view->productId, $view->supermarketId, $view->name, $view->brand, $view->available);
        }

        return $products;
    }
}
