<?php

declare(strict_types=1);

namespace App\Receipts\Application\ReadReceipt;

use App\Receipts\Domain\ValueObject\ReadBy;

/**
 * What a reader found on the photo. Anything it could not read is left out (PD-62).
 */
final readonly class ReadReceiptData
{
    /**
     * @param string|null        $date  "YYYY-MM-DD"
     * @param list<ReadLineData> $lines
     */
    public function __construct(
        public ReadBy $readBy,
        public ?string $date,
        public ?int $totalCents,
        public array $lines,
    ) {
    }
}
