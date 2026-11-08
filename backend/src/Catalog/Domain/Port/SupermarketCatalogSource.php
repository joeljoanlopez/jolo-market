<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Port;

use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * The online catalog of one supermarket chain. There is one adapter per chain (PD-1).
 */
interface SupermarketCatalogSource
{
    public function supermarketId(): SupermarketId;

    /**
     * Every product the chain sells at the given postal code (PD-5).
     *
     * @return iterable<SourceProduct>
     */
    public function fetchCatalog(PostalCode $postalCode): iterable;
}
