<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Source;

/**
 * Turns a decimal euro amount from a store ("16.75", "2.354") into cents without floats,
 * rounding half-up (PD-21). Anything else yields null.
 */
final class DecimalCents
{
    private const string PATTERN = '/^(\d+)(?:\.(\d+))?$/D';

    public static function fromString(string $amount): ?int
    {
        if (1 !== preg_match(self::PATTERN, trim($amount), $matches)) {
            return null;
        }

        $fraction = str_pad($matches[2] ?? '', 3, '0');
        $thousandths = (int) ($matches[1].substr($fraction, 0, 3));

        return intdiv($thousandths + 5, 10);
    }
}
