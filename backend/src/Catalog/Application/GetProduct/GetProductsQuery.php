<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

final readonly class GetProductsQuery
{
    /**
     * @param list<string> $productIds
     */
    public function __construct(public array $productIds)
    {
    }
}
