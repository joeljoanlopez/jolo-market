<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetPriceHistory;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidPriceHistoryRange extends InvalidArgument
{
    public static function notADate(string $name, string $value): self
    {
        return new self(\sprintf('The "%s" date must have the format YYYY-MM-DD, got "%s".', $name, $value));
    }

    public static function startAfterEnd(string $from, string $to): self
    {
        return new self(\sprintf('The "from" date (%s) must not be after the "to" date (%s).', $from, $to));
    }
}
