<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidItemSection extends InvalidArgument
{
    public static function unknown(string $section): self
    {
        return new self(\sprintf('"%s" is not a section id.', $section));
    }
}
