<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

final readonly class RemoveLineCommand
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $receiptId,
        public string $lineId,
    ) {
    }
}
