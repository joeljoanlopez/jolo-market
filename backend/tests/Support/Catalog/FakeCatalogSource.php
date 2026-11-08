<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;

final class FakeCatalogSource implements SupermarketCatalogSource
{
    /** @var list<PostalCode> */
    public private(set) array $requestedPostalCodes = [];

    /**
     * @param list<SourceProduct> $products
     * @param \Throwable|null     $failure  thrown after every product has been returned
     */
    public function __construct(
        private readonly string $supermarketId,
        private readonly array $products,
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public static function failing(string $supermarketId, \Throwable $failure): self
    {
        return new self($supermarketId, [], $failure);
    }

    public function supermarketId(): SupermarketId
    {
        return SupermarketId::fromString($this->supermarketId);
    }

    public function fetchCatalog(PostalCode $postalCode): iterable
    {
        $this->requestedPostalCodes[] = $postalCode;

        yield from $this->products;

        if (null !== $this->failure) {
            throw $this->failure;
        }
    }
}
