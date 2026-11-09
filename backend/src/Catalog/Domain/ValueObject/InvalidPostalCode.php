<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidPostalCode extends InvalidArgument
{
    public static function malformed(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid Spanish postal code.', $value));
    }
}
