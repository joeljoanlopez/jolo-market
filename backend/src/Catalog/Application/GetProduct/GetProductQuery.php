<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

final readonly class GetProductQuery
{
    public function __construct(public string $productId)
    {
    }
}
