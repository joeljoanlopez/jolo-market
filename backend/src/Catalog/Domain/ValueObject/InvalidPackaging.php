<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidPackaging extends InvalidArgument
{
    public static function notPositive(): self
    {
        return new self('A packaging quantity must be greater than zero.');
    }

    public static function notADecimal(string $quantity): self
    {
        return new self(\sprintf('"%s" is not a decimal quantity.', $quantity));
    }

    public static function tooPrecise(string $quantity): self
    {
        return new self(\sprintf('"%s" is more precise than a thousandth of the normalized unit.', $quantity));
    }
}
