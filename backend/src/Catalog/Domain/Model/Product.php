<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Service\UnitPriceCalculator;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\ProductDetails;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Domain\ValueObject\UnitPrice;
use App\Shared\Domain\ValueObject\Money;

/**
 * A product of one supermarket, identified there by its external id.
 *
 * The unit price is always derived from the current price and packaging, so it can never
 * disagree with them. Unknown packaging means no unit price (an explicit state, not a guess).
 */
final class Product
{
    private ?UnitPrice $unitPrice;

    private bool $available = true;

    private function __construct(
        private readonly ProductId $id,
        private readonly SupermarketId $supermarketId,
        private readonly ExternalProductId $externalId,
        private ProductDetails $details,
        private Money $currentPrice,
        private ?Packaging $packaging,
        private \DateTimeImmutable $lastSeenAt,
    ) {
        $this->unitPrice = self::unitPriceFor($currentPrice, $packaging);
    }

    public static function register(
        ProductId $id,
        SupermarketId $supermarketId,
        ExternalProductId $externalId,
        ProductDetails $details,
        Money $price,
        ?Packaging $packaging,
        \DateTimeImmutable $seenAt,
    ): self {
        return new self($id, $supermarketId, $externalId, $details, $price, $packaging, $seenAt);
    }

    /**
     * Rebuilds a stored product. Only persistence adapters call it.
     */
    public static function reconstitute(
        ProductId $id,
        SupermarketId $supermarketId,
        ExternalProductId $externalId,
        ProductDetails $details,
        Money $currentPrice,
        ?Packaging $packaging,
        \DateTimeImmutable $lastSeenAt,
        bool $available,
    ): self {
        $product = new self($id, $supermarketId, $externalId, $details, $currentPrice, $packaging, $lastSeenAt);
        $product->available = $available;

        return $product;
    }

    /**
     * The source returned the product again: take its latest data and make it available.
     */
    public function updateFromSource(
        ProductDetails $details,
        Money $price,
        ?Packaging $packaging,
        \DateTimeImmutable $seenAt,
    ): void {
        $this->details = $details;
        $this->currentPrice = $price;
        $this->packaging = $packaging;
        $this->unitPrice = self::unitPriceFor($price, $packaging);
        $this->lastSeenAt = $seenAt;
        $this->available = true;
    }

    /**
     * The last ingestion of its supermarket did not return the product.
     */
    public function markUnavailable(): void
    {
        $this->available = false;
    }

    public function captureSnapshot(\DateTimeImmutable $capturedAt): PriceSnapshot
    {
        return PriceSnapshot::capture($this->id, $this->currentPrice, $this->unitPrice, $capturedAt);
    }

    public function id(): ProductId
    {
        return $this->id;
    }

    public function supermarketId(): SupermarketId
    {
        return $this->supermarketId;
    }

    public function externalId(): ExternalProductId
    {
        return $this->externalId;
    }

    public function details(): ProductDetails
    {
        return $this->details;
    }

    public function currentPrice(): Money
    {
        return $this->currentPrice;
    }

    public function packaging(): ?Packaging
    {
        return $this->packaging;
    }

    public function unitPrice(): ?UnitPrice
    {
        return $this->unitPrice;
    }

    public function lastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    private static function unitPriceFor(Money $price, ?Packaging $packaging): ?UnitPrice
    {
        return null === $packaging ? null : new UnitPriceCalculator()->calculate($price, $packaging);
    }
}
