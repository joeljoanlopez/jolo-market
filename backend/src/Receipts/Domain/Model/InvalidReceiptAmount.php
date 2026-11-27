<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * Totals and line amounts are positive cents (PD-19, PD-66).
 */
final class InvalidReceiptAmount extends InvalidArgument
{
    public static function notPositive(): self
    {
        return new self('Every receipt amount must be more than 0.');
    }
}
