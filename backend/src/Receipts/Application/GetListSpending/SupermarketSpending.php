<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListSpending;

final readonly class SupermarketSpending
{
    public function __construct(
        public string $supermarketId,
        public string $supermarketName,
        public int $totalCents,
    ) {
    }
}
