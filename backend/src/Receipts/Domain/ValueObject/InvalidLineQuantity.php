<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidLineQuantity extends InvalidArgument
{
    public static function outOfRange(QuantityUnit $unit, int $min, int $max): self
    {
        return new self(\sprintf('A quantity in %s must be between %d and %d.', $unit->value, $min, $max));
    }
}
