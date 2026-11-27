<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Receipts\Domain\ValueObject\ReceiptId;

/**
 * Asks the worker to read a receipt later, so the upload answers at once (PD-65).
 */
interface ReceiptReadingQueue
{
    public function requestReading(ReceiptId $receiptId): void;
}
