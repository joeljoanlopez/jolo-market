<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * Only product lines have a quantity and a product (PD-66, PD-67).
 */
final class NotAProductLine extends InvalidArgument
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Receipt line "%s" is a discount line.', $id));
    }
}
