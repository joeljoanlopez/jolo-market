<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidListName extends InvalidArgument
{
    public static function length(int $maxLength): self
    {
        return new self(\sprintf('A list name needs 1 to %d characters.', $maxLength));
    }
}
