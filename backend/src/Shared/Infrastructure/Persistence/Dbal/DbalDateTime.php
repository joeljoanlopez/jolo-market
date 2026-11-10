<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Dbal;

/**
 * DATETIME(6) columns hold UTC with microseconds.
 */
final class DbalDateTime
{
    private const string FORMAT = 'Y-m-d H:i:s.u';

    public static function toDatabase(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
    }

    public static function fromDatabase(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}
