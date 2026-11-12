<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetPriceHistory;

final readonly class PriceHistory
{
    /**
     * @param list<PriceHistoryPoint> $points oldest first
     */
    public function __construct(public string $productId, public array $points)
    {
    }
}
