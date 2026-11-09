<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\Clock;

/**
 * Deterministic clock for tests. It only moves when the test says so.
 */
final class FixedClock implements Clock
{
    private function __construct(private \DateTimeImmutable $now)
    {
    }

    public static function at(string $utcDateTime): self
    {
        return new self(new \DateTimeImmutable($utcDateTime, new \DateTimeZone('UTC')));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(\DateInterval $interval): void
    {
        $this->now = $this->now->add($interval);
    }

    public function advanceMicroseconds(int $microseconds): void
    {
        $this->now = $this->now->modify(\sprintf('+%d microseconds', $microseconds));
    }
}
