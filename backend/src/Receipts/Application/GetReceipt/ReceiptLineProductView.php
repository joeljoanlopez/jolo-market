<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceipt;

/**
 * A linked or suggested product. The name is null when the catalog no longer knows the product,
 * so the line is shown by its text.
 */
final readonly class ReceiptLineProductView
{
    public function __construct(
        public string $productId,
        public ?string $name,
        public ?string $brand,
        public bool $available,
    ) {
    }
}
