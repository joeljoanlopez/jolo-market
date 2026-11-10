<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidUsername extends InvalidArgument
{
    public static function notAllowed(string $value): self
    {
        return new self(\sprintf(
            '"%s" is not a valid username (3 to 32 characters: a-z, digits, ".", "_" and "-", starting with a letter or a digit).',
            $value,
        ));
    }
}
