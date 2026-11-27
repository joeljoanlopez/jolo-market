<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetListReceipts;

use App\Receipts\Application\GetReceipt\ReceiptUserView;

final readonly class ReceiptSummary
{
    /**
     * @param 'reading'|'draft'|'confirmed' $status
     * @param string|null                   $date       "YYYY-MM-DD"
     * @param string                        $uploadedAt ISO 8601
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $supermarketId,
        public string $supermarketName,
        public ?string $date,
        public ?int $totalCents,
        public ReceiptUserView $uploader,
        public string $uploadedAt,
    ) {
    }
}
