<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceiptPhoto;

final readonly class GetReceiptPhotoQuery
{
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $receiptId,
    ) {
    }
}
