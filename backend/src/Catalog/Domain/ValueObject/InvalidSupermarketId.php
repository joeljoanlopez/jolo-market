<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidSupermarketId extends InvalidArgument
{
    public static function notASlug(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid supermarket id (lowercase letters, digits and inner hyphens).', $value));
    }
}
