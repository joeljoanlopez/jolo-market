<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceipt;

final readonly class ReceiptLineView
{
    /**
     * @param 'product'|'discount' $kind
     * @param 'units'|'kg'|null    $unit
     * @param int|null             $quantity units, or thousandths of a kg
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $text,
        public ?string $unit,
        public ?int $quantity,
        public int $amountCents,
        public ?ReceiptLineProductView $linkedProduct,
        public ?ReceiptLineProductView $suggestedProduct,
    ) {
    }
}
