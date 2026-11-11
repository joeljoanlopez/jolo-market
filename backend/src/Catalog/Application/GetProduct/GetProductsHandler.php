<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

use App\Catalog\Domain\ValueObject\ProductId;
use App\Shared\Domain\ValueObject\InvalidUuid;

/**
 * Query API for other contexts (E4.14): shopping lists read the current data of their products,
 * available or not (PD-22).
 */
final readonly class GetProductsHandler
{
    public function __construct(private ProductViewReadModel $readModel)
    {
    }

    /**
     * @return array<string, ProductView> by product id, in the order asked; unknown or malformed ids are left out
     */
    public function __invoke(GetProductsQuery $query): array
    {
        $products = [];
        foreach ($query->productIds as $productId) {
            try {
                $id = ProductId::fromString($productId);
            } catch (InvalidUuid) {
                continue;
            }
            if (\array_key_exists($id->value(), $products)) {
                continue;
            }

            $product = $this->readModel->find($id);
            if (null !== $product) {
                $products[$id->value()] = $product;
            }
        }

        return $products;
    }
}
