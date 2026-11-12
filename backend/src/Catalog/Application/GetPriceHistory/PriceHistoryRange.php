<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetPriceHistory;

/**
 * Whole days in Europe/Madrid, both ends inclusive (PD-47), turned into UTC instants: snapshots captured at or after
 * $from and before $before.
 */
final readonly class PriceHistoryRange
{
    private const string TIME_ZONE = 'Europe/Madrid';

    private function __construct(public ?\DateTimeImmutable $from, public ?\DateTimeImmutable $before)
    {
    }

    public static function ofDays(?string $from, ?string $to): self
    {
        $start = null === $from ? null : self::startOfDay('from', $from);
        // The start of the next day keeps "to" inclusive, and modifying the local date follows summer time.
        $end = null === $to ? null : self::startOfDay('to', $to)->modify('+1 day');

        if (null !== $start && null !== $end && $start >= $end) {
            throw InvalidPriceHistoryRange::startAfterEnd((string) $from, (string) $to);
        }

        $utc = new \DateTimeZone('UTC');

        return new self($start?->setTimezone($utc), $end?->setTimezone($utc));
    }

    private static function startOfDay(string $name, string $date): \DateTimeImmutable
    {
        $day = 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone(self::TIME_ZONE))
            : false;

        // createFromFormat rolls impossible dates over (2026-02-30 → 2026-03-02), so the round trip rejects them.
        if (false === $day || $day->format('Y-m-d') !== $date) {
            throw InvalidPriceHistoryRange::notADate($name, $date);
        }

        return $day;
    }
}
