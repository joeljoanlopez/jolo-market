<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceipt;

final readonly class GetReceiptQuery
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $receiptId,
    ) {
    }
}
