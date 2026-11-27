<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidReceiptDate extends InvalidArgument
{
    public static function malformed(string $value): self
    {
        return new self(\sprintf('"%s" is not a day (YYYY-MM-DD).', $value));
    }

    public static function inTheFuture(string $value): self
    {
        return new self(\sprintf('The receipt date %s is in the future.', $value));
    }
}
