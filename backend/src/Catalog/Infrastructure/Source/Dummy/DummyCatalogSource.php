<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Source\Dummy;

use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\Clock;

/**
 * An offline stand-in for the adapter of one chain, registered only in the development
 * environment (PD-53). It never makes a request: it returns the dummy catalog of the run date,
 * whatever the postal code.
 */
final readonly class DummyCatalogSource implements SupermarketCatalogSource
{
    public function __construct(
        private string $supermarket,
        private Clock $clock,
    ) {
    }

    public function supermarketId(): SupermarketId
    {
        return SupermarketId::fromString($this->supermarket);
    }

    public function fetchCatalog(PostalCode $postalCode): iterable
    {
        return DummyCatalog::forSupermarket($this->supermarket, $this->clock->now());
    }
}
