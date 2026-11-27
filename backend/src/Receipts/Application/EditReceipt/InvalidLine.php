<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * PD-66: a line is a product line, with a quantity in units or kg, or a discount line without one.
 */
final class InvalidLine extends InvalidArgument
{
    public static function unknownKind(string $kind): self
    {
        return new self(\sprintf('"%s" is not a line kind: use "product" or "discount".', $kind));
    }

    public static function unknownUnit(string $unit): self
    {
        return new self(\sprintf('"%s" is not a quantity unit: use "units" or "kg".', $unit));
    }

    public static function quantityNeeded(): self
    {
        return new self('A product line needs a quantity and its unit.');
    }

    public static function noQuantityForDiscount(): self
    {
        return new self('A discount line has no quantity.');
    }
}
