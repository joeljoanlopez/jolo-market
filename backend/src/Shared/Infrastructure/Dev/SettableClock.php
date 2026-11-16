<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Dev;

use App\Shared\Domain\Clock;

/**
 * A clock that can be moved to another instant, only for the development environment: the
 * price history backfill (PD-55) runs one ingestion per past day with the clock on that day.
 * Until it is set, it tells the time of the clock it wraps.
 */
final class SettableClock implements Clock
{
    private ?\DateTimeImmutable $now = null;

    public function __construct(private readonly Clock $clock)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now ?? $this->clock->now();
    }

    public function set(\DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function reset(): void
    {
        $this->now = null;
    }
}
