<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Port;

/**
 * A product as a supermarket source returns it, before any validation or normalization.
 *
 * Values are plain on purpose: one malformed product must not stop the source from
 * returning the rest of the catalog. Ingestion validates each product on its own.
 */
final readonly class SourceProduct
{
    /**
     * @param list<string> $categoryPath            from the root category down
     * @param string|null  $packaging               the packaging text the store shows, such as "6 x 1 L"
     * @param int|null     $referenceUnitPriceCents the store's own price per $referenceUnit; adapters set it
     *                                              only when the size is approximate (PD-29)
     * @param string|null  $referenceUnit           "kg", "l" or "unit"
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public int $priceCents,
        public ?string $packaging = null,
        public ?string $brand = null,
        public array $categoryPath = [],
        public ?string $imageUrl = null,
        public ?int $referenceUnitPriceCents = null,
        public ?string $referenceUnit = null,
    ) {
    }
}
