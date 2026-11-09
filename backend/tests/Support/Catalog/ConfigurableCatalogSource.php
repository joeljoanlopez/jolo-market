<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Catalog source registered in the test container (config/services/catalog.yaml, when@test).
 * Functional tests fetch it and say what the next fetch returns.
 */
final class ConfigurableCatalogSource implements SupermarketCatalogSource
{
    /** @var list<SourceProduct> */
    private array $products = [];

    private ?\Throwable $failure = null;

    public function __construct(private readonly string $supermarketId)
    {
    }

    /**
     * @param list<SourceProduct> $products
     */
    public function willReturn(array $products): void
    {
        $this->products = $products;
        $this->failure = null;
    }

    public function willFail(\Throwable $failure): void
    {
        $this->products = [];
        $this->failure = $failure;
    }

    public function supermarketId(): SupermarketId
    {
        return SupermarketId::fromString($this->supermarketId);
    }

    public function fetchCatalog(PostalCode $postalCode): iterable
    {
        yield from $this->products;

        if (null !== $this->failure) {
            throw $this->failure;
        }
    }
}
