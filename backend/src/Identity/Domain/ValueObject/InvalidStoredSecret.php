<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidStoredSecret extends InvalidArgument
{
    public static function empty(string $kind): self
    {
        return new self(\sprintf('A %s cannot be empty.', $kind));
    }
}
