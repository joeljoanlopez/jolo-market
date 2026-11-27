<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Receipts\Domain\ValueObject\ReadBy;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Shared\Domain\ValueObject\Money;

/**
 * What reading the photo gave (PD-65, PD-112): anything that could not be read is left out, and
 * a failed reading gives nothing, read by none (PD-114).
 */
final readonly class ReadResult
{
    /**
     * @param list<ReceiptLine> $lines
     */
    private function __construct(
        public ReadBy $readBy,
        public ?ReceiptDate $date,
        public ?Money $total,
        public array $lines,
    ) {
        if (null !== $total && 0 === $total->cents()) {
            throw InvalidReceiptAmount::notPositive();
        }
        if (\count($lines) > Receipt::MAX_LINES) {
            throw TooManyReceiptLines::max(Receipt::MAX_LINES);
        }
    }

    /**
     * @param list<ReceiptLine> $lines
     */
    public static function read(ReadBy $readBy, ?ReceiptDate $date, ?Money $total, array $lines): self
    {
        if (ReadBy::NONE === $readBy) {
            throw new \InvalidArgumentException('A successful reading is by the AI or the OCR.');
        }

        return new self($readBy, $date, $total, $lines);
    }

    public static function failed(): self
    {
        return new self(ReadBy::NONE, null, null, []);
    }
}
