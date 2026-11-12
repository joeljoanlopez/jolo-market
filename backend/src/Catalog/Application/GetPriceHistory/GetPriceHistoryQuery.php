<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetPriceHistory;

final readonly class GetPriceHistoryQuery
{
    public PriceHistoryRange $range;

    /**
     * @param string|null $from first day (YYYY-MM-DD, Europe/Madrid, inclusive); null for no lower bound (PD-47)
     * @param string|null $to   last day (YYYY-MM-DD, Europe/Madrid, inclusive); null for no upper bound
     */
    public function __construct(public string $productId, ?string $from = null, ?string $to = null)
    {
        $this->range = PriceHistoryRange::ofDays($from, $to);
    }
}
