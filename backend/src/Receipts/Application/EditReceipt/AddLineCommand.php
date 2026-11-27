<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

final readonly class AddLineCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $receiptId,
        // "product" or "discount"
        public string $kind,
        public string $text,
        // "units" or "kg" for a product line, null for a discount line
        public ?string $unit,
        // units, or thousandths of a kg
        public ?int $quantity,
        public int $amountCents,
    ) {
    }
}
