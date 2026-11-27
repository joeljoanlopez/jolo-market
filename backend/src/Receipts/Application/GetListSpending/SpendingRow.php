<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListSpending;

final readonly class SpendingRow
{
    /**
     * @param string $month "YYYY-MM" of the receipt date
     */
    public function __construct(
        public string $month,
        public string $supermarketId,
        public int $totalCents,
    ) {
    }
}
