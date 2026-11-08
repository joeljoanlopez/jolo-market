<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\UnitPrice;
use App\Shared\Domain\ValueObject\Money;

final class UnitPriceCalculator
{
    /**
     * Price ÷ normalized quantity, rounded to whole cents half-up (PD-21).
     */
    public function calculate(Money $price, Packaging $packaging): UnitPrice
    {
        // cents per unit = cents * 1000 / thousandths. Adding half the divisor before the
        // integer division rounds half-up without leaving integer arithmetic.
        $dividend = $price->cents() * 1000;
        $divisor = $packaging->thousandths();
        $cents = intdiv(2 * $dividend + $divisor, 2 * $divisor);

        return UnitPrice::of(Money::eur($cents), $packaging->unit());
    }
}
