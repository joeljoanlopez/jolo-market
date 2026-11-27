<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

final readonly class UpdateLineCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $receiptId,
        public string $lineId,
        // each null field is kept as it is; unit and quantity go together
        public ?string $text,
        public ?string $unit,
        public ?int $quantity,
        public ?int $amountCents,
    ) {
    }
}
