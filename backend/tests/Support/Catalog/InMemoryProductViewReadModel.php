<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\GetProduct\ProductView;
use App\Catalog\Application\GetProduct\ProductViewReadModel;
use App\Catalog\Domain\ValueObject\ProductId;

final class InMemoryProductViewReadModel implements ProductViewReadModel
{
    /** @var array<string, ProductView> */
    private array $products = [];

    public function add(ProductView $product): void
    {
        $this->products[$product->productId] = $product;
    }

    public function find(ProductId $id): ?ProductView
    {
        return $this->products[$id->value()] ?? null;
    }
}
