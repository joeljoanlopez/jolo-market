<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\QuantityUnit;

final class LineQuantities
{
    /**
     * @return LineQuantity|null null when neither unit nor quantity is given
     */
    public static function from(?string $unit, ?int $quantity): ?LineQuantity
    {
        if (null === $unit && null === $quantity) {
            return null;
        }
        if (null === $unit || null === $quantity) {
            throw InvalidLine::quantityNeeded();
        }

        return LineQuantity::of(QuantityUnit::tryFrom($unit) ?? throw InvalidLine::unknownUnit($unit), $quantity);
    }
}
