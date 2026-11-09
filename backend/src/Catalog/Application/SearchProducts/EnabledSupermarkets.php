<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * The supermarkets that are shown: ENABLED_SUPERMARKETS (PD-24).
 */
interface EnabledSupermarkets
{
    /**
     * @return list<SupermarketId>
     */
    public function enabledSupermarkets(): array;
}
