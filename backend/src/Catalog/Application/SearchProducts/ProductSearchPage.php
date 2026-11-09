<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

final readonly class ProductSearchPage
{
    /**
     * @param list<ProductSearchResult> $results                      ordered as PD-33 says
     * @param int                       $total                        results of the whole query, not only this page
     * @param array<string, int>        $cheapestUnitPriceCentsByUnit lowest unit price per unit over the whole query,
     *                                                                so the cheapest of each unit group can be highlighted (PD-33)
     */
    public function __construct(
        public array $results,
        public int $total,
        public int $page,
        public int $perPage,
        public array $cheapestUnitPriceCentsByUnit,
    ) {
    }
}
