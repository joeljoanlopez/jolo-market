<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

final readonly class LinkLineCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $receiptId,
        public string $lineId,
        public string $productId,
    ) {
    }
}
