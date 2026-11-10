<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidActivationToken extends InvalidArgument
{
    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('An activation token needs at least %d characters.', $minLength));
    }
}
