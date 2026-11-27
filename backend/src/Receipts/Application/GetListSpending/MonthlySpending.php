<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListSpending;

final readonly class MonthlySpending
{
    /**
     * @param string                    $month        "YYYY-MM"
     * @param list<SupermarketSpending> $supermarkets by supermarket name
     */
    public function __construct(
        public string $month,
        public int $totalCents,
        public array $supermarkets,
    ) {
    }
}
