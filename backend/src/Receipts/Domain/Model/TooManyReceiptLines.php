<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Shared\Domain\Exception\InvalidArgument;

final class TooManyReceiptLines extends InvalidArgument
{
    public static function max(int $max): self
    {
        return new self(\sprintf('A receipt has at most %d lines.', $max));
    }
}
