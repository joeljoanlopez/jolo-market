<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidQuantity extends InvalidArgument
{
    public static function outOfRange(int $min, int $max): self
    {
        return new self(\sprintf('A quantity must be a whole number from %d to %d.', $min, $max));
    }
}
