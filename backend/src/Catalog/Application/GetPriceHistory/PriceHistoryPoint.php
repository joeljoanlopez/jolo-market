<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetPriceHistory;

/**
 * One price snapshot of a product (PD-6). The unit is "kg", "l" or "unit", or null with the unit price.
 */
final readonly class PriceHistoryPoint
{
    public function __construct(
        public \DateTimeImmutable $capturedAt,
        public int $priceCents,
        public ?int $unitPriceCents,
        public ?string $unitPriceUnit,
    ) {
    }
}
