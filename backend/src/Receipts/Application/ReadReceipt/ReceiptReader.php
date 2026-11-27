<?php

declare(strict_types=1);

namespace App\Receipts\Application\ReadReceipt;

use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\SupermarketId;

/**
 * Reads a receipt photo: the AI first, the OCR as fallback (PD-112).
 */
interface ReceiptReader
{
    /**
     * @return ReadReceiptData|null what was read and by whom, or null when every reader failed (PD-65, PD-114)
     */
    public function read(string $photo, PhotoType $photoType, SupermarketId $supermarketId): ?ReadReceiptData;
}
