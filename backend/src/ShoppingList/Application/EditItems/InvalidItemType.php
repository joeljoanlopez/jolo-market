<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidItemType extends InvalidArgument
{
    public static function create(): self
    {
        return new self('An item type must be "text" or "product".');
    }
}
