<?php

declare(strict_types=1);

namespace App\Receipts\Application;

/**
 * A catalog product as receipts show it, available or not (PD-22).
 */
final readonly class ReceiptProduct
{
    public function __construct(
        public string $productId,
        public string $supermarketId,
        public string $name,
        public ?string $brand,
        public bool $available,
    ) {
    }
}
