<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidLineText extends InvalidArgument
{
    public static function length(int $maxLength): self
    {
        return new self(\sprintf('A receipt line needs a text of 1 to %d characters.', $maxLength));
    }
}
