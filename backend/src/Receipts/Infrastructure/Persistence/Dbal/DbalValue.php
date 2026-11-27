<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Persistence\Dbal;

/**
 * Narrows the mixed values of a fetched row to the types of their columns.
 */
final class DbalValue
{
    public static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \UnexpectedValueException('Expected a string column.');
    }

    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : throw new \UnexpectedValueException('Expected an integer column.');
    }

    public static function nullableInt(mixed $value): ?int
    {
        return null === $value ? null : self::int($value);
    }
}
