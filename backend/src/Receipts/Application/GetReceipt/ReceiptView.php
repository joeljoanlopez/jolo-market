<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceipt;

final readonly class ReceiptView
{
    /**
     * @param 'reading'|'draft'|'confirmed' $status
     * @param string|null                   $date            "YYYY-MM-DD"
     * @param string                        $uploadedAt      ISO 8601
     * @param int|null                      $differenceCents products − discounts − total; null without a total (PD-66)
     * @param list<ReceiptLineView>         $lines
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
        public ?int $differenceCents,
        public array $lines,
    ) {
    }
}
