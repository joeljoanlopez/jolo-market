<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

final readonly class UpdateReceiptCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $receiptId,
        // "YYYY-MM-DD"; null keeps the current date
        public ?string $date,
        // null keeps the current total
        public ?int $totalCents,
    ) {
    }
}
