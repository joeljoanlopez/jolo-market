<?php

declare(strict_types=1);

namespace App\Catalog\Application\PrunePriceHistory;

use App\Catalog\Domain\Port\PriceSnapshotRepository;
use App\Shared\Domain\Clock;

/**
 * Deletes price snapshots older than the retention period, keeping the latest one of each product (PD-46).
 */
final readonly class PrunePriceHistoryHandler
{
    public function __construct(private PriceSnapshotRepository $snapshots, private Clock $clock)
    {
    }

    public function __invoke(PrunePriceHistoryCommand $command): PrunePriceHistoryResult
    {
        $cutoff = self::monthsBefore($this->clock->now(), $command->retentionMonths);

        return new PrunePriceHistoryResult($cutoff, $this->snapshots->deleteCapturedBefore($cutoff));
    }

    /**
     * Same day and time N months earlier, or the last day of that month when it is shorter. PHP's "-N months" would
     * roll 31 February over to 3 March and keep a few days less than promised.
     */
    private static function monthsBefore(\DateTimeImmutable $now, int $months): \DateTimeImmutable
    {
        $firstOfMonth = $now->setDate((int) $now->format('Y'), (int) $now->format('n'), 1)->modify(\sprintf('-%d months', $months));
        $day = min((int) $now->format('j'), (int) $firstOfMonth->format('t'));

        return $firstOfMonth->setDate((int) $firstOfMonth->format('Y'), (int) $firstOfMonth->format('n'), $day);
    }
}
