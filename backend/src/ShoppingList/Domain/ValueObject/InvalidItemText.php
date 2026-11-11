<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidItemText extends InvalidArgument
{
    public static function length(int $maxLength): self
    {
        return new self(\sprintf('An item text needs 1 to %d characters.', $maxLength));
    }
}
