<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidItemPosition extends InvalidArgument
{
    public static function negative(): self
    {
        return new self('An item position is a 0-based index and cannot be negative.');
    }
}
