<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * A non-negative amount in integer cents (PD-19). Floats are never used for money.
 */
final readonly class Money
{
    private function __construct(
        private int $cents,
        private Currency $currency,
    ) {
        if ($cents < 0) {
            throw InvalidMoneyAmount::negative($cents);
        }
    }

    public static function eur(int $cents): self
    {
        return new self($cents, Currency::EUR);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw InvalidMoneyAmount::negativeMultiplier($multiplier);
        }

        return new self($this->cents * $multiplier, $this->currency);
    }

    public function equals(self $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    /**
     * @return -1|0|1
     */
    public function compareTo(self $other): int
    {
        return $this->cents <=> $other->cents;
    }

    public function isGreaterThan(self $other): bool
    {
        return 1 === $this->compareTo($other);
    }

    public function isLessThan(self $other): bool
    {
        return -1 === $this->compareTo($other);
    }
}
