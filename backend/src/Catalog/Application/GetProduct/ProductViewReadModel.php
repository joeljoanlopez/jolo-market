<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

use App\Catalog\Domain\ValueObject\ProductId;

interface ProductViewReadModel
{
    /**
     * Finds the product whether it is available or not, so lists can still show it (PD-22).
     */
    public function find(ProductId $id): ?ProductView;
}
