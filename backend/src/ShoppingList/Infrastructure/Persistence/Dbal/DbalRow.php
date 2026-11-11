<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Persistence\Dbal;

/**
 * Narrows the mixed values of a fetched row to the types of their columns.
 */
final class DbalRow
{
    public static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \UnexpectedValueException('Expected a string column.');
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : throw new \UnexpectedValueException('Expected an integer column.');
    }
}
