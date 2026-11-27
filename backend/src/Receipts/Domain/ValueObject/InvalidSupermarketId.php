<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidSupermarketId extends InvalidArgument
{
    public static function notASlug(string $value): self
    {
        return new self(\sprintf('"%s" is not a supermarket id.', $value));
    }
}
