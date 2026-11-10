<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * Its messages never include the password itself.
 */
final class WeakPassword extends InvalidArgument
{
    public static function notValidText(): self
    {
        return new self('The password is not valid UTF-8 text.');
    }

    public static function wrongLength(int $min, int $max): self
    {
        return new self(\sprintf('The password must have between %d and %d characters.', $min, $max));
    }

    public static function missingCharacterKind(): self
    {
        return new self('The password needs an uppercase letter, a lowercase letter, a digit and a special character.');
    }
}
