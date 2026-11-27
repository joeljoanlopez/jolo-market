<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Application\ReceiptReadingQueue;
use App\Receipts\Domain\ValueObject\ReceiptId;

final class RecordingReceiptReadingQueue implements ReceiptReadingQueue
{
    /** @var list<string> */
    private array $requested = [];

    public function requestReading(ReceiptId $receiptId): void
    {
        $this->requested[] = $receiptId->value();
    }

    /**
     * @return list<string>
     */
    public function requested(): array
    {
        return $this->requested;
    }
}
