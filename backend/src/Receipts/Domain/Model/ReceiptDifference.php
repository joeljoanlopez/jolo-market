<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Shared\Domain\ValueObject\Money;

/**
 * Products − discounts − total (PD-66). Money is never negative (PD-19), so the sign is kept apart:
 * 1 when the lines add up to more than the total, -1 when to less, 0 when they match.
 */
final readonly class ReceiptDifference
{
    /**
     * @param -1|0|1 $sign
     */
    public function __construct(
        public Money $amount,
        public int $sign,
    ) {
    }

    public static function between(Money $lines, Money $discounts, Money $total): self
    {
        $signed = $lines->cents() - $discounts->cents() - $total->cents();

        return new self(Money::eur(abs($signed)), $signed <=> 0);
    }

    public function signedCents(): int
    {
        return $this->sign * $this->amount->cents();
    }
}
