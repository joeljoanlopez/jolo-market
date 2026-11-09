<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

/**
 * Narrows the mixed values of a fetched row to the types of their columns.
 */
final class DbalColumn
{
    public static function string(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new \UnexpectedValueException('Expected a string column.');
        }

        return $value;
    }

    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    public static function int(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new \UnexpectedValueException('Expected an integer column.');
        }

        return (int) $value;
    }

    public static function nullableInt(mixed $value): ?int
    {
        return null === $value ? null : self::int($value);
    }
}
