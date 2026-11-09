<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

use App\Catalog\Domain\ValueObject\ProductId;
use App\Shared\Domain\ValueObject\InvalidUuid;

final readonly class GetProductHandler
{
    public function __construct(private ProductViewReadModel $readModel)
    {
    }

    public function __invoke(GetProductQuery $query): ProductView
    {
        try {
            $id = ProductId::fromString($query->productId);
        } catch (InvalidUuid) {
            // No product can have a malformed id, so it is just as unknown as a well-formed one.
            throw ProductNotFound::withId($query->productId);
        }

        return $this->readModel->find($id) ?? throw ProductNotFound::withId($query->productId);
    }
}
