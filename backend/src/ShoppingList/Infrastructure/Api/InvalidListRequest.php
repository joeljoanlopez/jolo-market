<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Api;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidListRequest extends InvalidArgument
{
    public static function wrongType(string $field, string $type): self
    {
        return new self(\sprintf('The field "%s" must be %s.', $field, $type));
    }

    public static function onlyCheckedItemsCanBeCleared(): self
    {
        return new self('Only checked items can be cleared: add "checked=true".');
    }
}
