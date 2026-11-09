<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\UnitPrice;
use App\Shared\Domain\ValueObject\Money;

/**
 * The price of a product at one ingestion run (PD-6). Append-only: it is never changed.
 */
final readonly class PriceSnapshot
{
    private function __construct(
        private ProductId $productId,
        private Money $price,
        private ?UnitPrice $unitPrice,
        private \DateTimeImmutable $capturedAt,
    ) {
    }

    public static function capture(ProductId $productId, Money $price, ?UnitPrice $unitPrice, \DateTimeImmutable $capturedAt): self
    {
        return new self($productId, $price, $unitPrice, $capturedAt);
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function unitPrice(): ?UnitPrice
    {
        return $this->unitPrice;
    }

    public function capturedAt(): \DateTimeImmutable
    {
        return $this->capturedAt;
    }
}
