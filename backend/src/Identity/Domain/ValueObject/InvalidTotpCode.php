<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidTotpCode extends InvalidArgument
{
    public static function notSixDigits(): self
    {
        return new self('A two-factor code has exactly six digits.');
    }
}
