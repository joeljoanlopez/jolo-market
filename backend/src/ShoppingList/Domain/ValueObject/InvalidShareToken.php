<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidShareToken extends InvalidArgument
{
    public static function malformed(): self
    {
        // The token itself is a secret, so it is never part of the message.
        return new self('A share token must be 22 to 64 URL-safe characters.');
    }
}
