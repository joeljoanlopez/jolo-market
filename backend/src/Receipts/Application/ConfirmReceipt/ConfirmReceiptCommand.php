<?php

declare(strict_types=1);

namespace App\Receipts\Application\ConfirmReceipt;

final readonly class ConfirmReceiptCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $receiptId,
    ) {
    }
}
