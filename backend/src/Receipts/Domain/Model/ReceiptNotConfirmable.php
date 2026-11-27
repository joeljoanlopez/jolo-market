<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * PD-66: a receipt needs a date and a total to be confirmed.
 */
final class ReceiptNotConfirmable extends InvalidArgument
{
    public static function missingDateOrTotal(): self
    {
        return new self('A receipt needs a date and a total to be confirmed.');
    }
}
