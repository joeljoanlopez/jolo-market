<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Dev;

use App\Shared\Infrastructure\Dev\SettableClock;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SettableClockTest extends TestCase
{
    #[Test]
    public function it_tells_the_time_of_the_clock_it_wraps_until_it_is_set(): void
    {
        $clock = new SettableClock(FixedClock::at('2026-11-14 10:00:00'));

        self::assertEquals(new \DateTimeImmutable('2026-11-14 10:00:00', new \DateTimeZone('UTC')), $clock->now());
    }

    #[Test]
    public function it_tells_the_time_it_was_set_to(): void
    {
        $clock = new SettableClock(FixedClock::at('2026-11-14 10:00:00'));

        $clock->set(new \DateTimeImmutable('2026-05-18 10:00:00', new \DateTimeZone('UTC')));

        self::assertEquals(new \DateTimeImmutable('2026-05-18 10:00:00', new \DateTimeZone('UTC')), $clock->now());
    }

    #[Test]
    public function it_goes_back_to_the_wrapped_clock_once_reset(): void
    {
        $wrapped = FixedClock::at('2026-11-14 10:00:00');
        $clock = new SettableClock($wrapped);
        $clock->set(new \DateTimeImmutable('2026-05-18 10:00:00'));

        $clock->reset();

        self::assertEquals($wrapped->now(), $clock->now());
    }
}
