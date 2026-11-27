<?php

declare(strict_types=1);

namespace App\Receipts\Application\ReadReceipt;

/**
 * One line read from the photo: a product line with its quantity, or a discount line without one.
 */
final readonly class ReadLineData
{
    /**
     * @param 'product'|'discount' $kind
     * @param 'units'|'kg'|null    $unit     null for a discount line
     * @param int|null             $quantity units, or thousandths of a kg
     */
    public function __construct(
        public string $kind,
        public string $text,
        public ?string $unit,
        public ?int $quantity,
        public int $amountCents,
    ) {
    }
}
