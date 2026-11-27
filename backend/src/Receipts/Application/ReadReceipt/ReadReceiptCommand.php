<?php

declare(strict_types=1);

namespace App\Receipts\Application\ReadReceipt;

final readonly class ReadReceiptCommand
{
    public function __construct(public string $receiptId)
    {
    }
}
