<?php

declare(strict_types=1);

namespace App\Receipts\Application\UploadReceipt;

final readonly class UploadReceiptCommand
{
    /**
     * @param string $photo the uploaded bytes
     */
    public function __construct(
        public string $actorId,
        public string $listId,
        public string $supermarketId,
        public string $photo,
    ) {
    }
}
