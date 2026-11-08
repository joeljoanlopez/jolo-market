<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Port;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\SupermarketId;

interface ProductRepository
{
    /**
     * Inserts the product or updates it when it already exists.
     */
    public function save(Product $product): void;

    public function findBySupermarketAndExternalId(SupermarketId $supermarketId, ExternalProductId $externalId): ?Product;

    /**
     * Products of the supermarket that are still available but were last seen before $instant.
     * Called at the end of a run, it returns the products the run did not see.
     *
     * @return iterable<Product>
     */
    public function findAvailableNotSeenSince(SupermarketId $supermarketId, \DateTimeImmutable $instant): iterable;
}
