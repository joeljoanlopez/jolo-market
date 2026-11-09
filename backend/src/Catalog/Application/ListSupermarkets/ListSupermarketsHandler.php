<?php

declare(strict_types=1);

namespace App\Catalog\Application\ListSupermarkets;

use App\Catalog\Application\SearchProducts\EnabledSupermarkets;

/**
 * The enabled supermarkets (PD-24), in the order of ENABLED_SUPERMARKETS.
 */
final readonly class ListSupermarketsHandler
{
    public function __construct(
        private EnabledSupermarkets $enabledSupermarkets,
        private SupermarketNames $names,
    ) {
    }

    /**
     * @return list<SupermarketSummary>
     */
    public function __invoke(ListSupermarketsQuery $query): array
    {
        $enabled = $this->enabledSupermarkets->enabledSupermarkets();
        $names = $this->names->namesOf($enabled);

        $supermarkets = [];
        foreach ($enabled as $supermarket) {
            // A slug with no catalog row has no name and no products to show.
            if (isset($names[$supermarket->value()])) {
                $supermarkets[] = new SupermarketSummary($supermarket->value(), $names[$supermarket->value()]);
            }
        }

        return $supermarkets;
    }
}
