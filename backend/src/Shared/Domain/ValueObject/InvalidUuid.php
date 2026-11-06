<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidUuid extends InvalidArgument
{
    public static function malformed(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid UUID.', $value));
    }
}
