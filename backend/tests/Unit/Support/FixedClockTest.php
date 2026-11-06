<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixedClockTest extends TestCase
{
    #[Test]
    public function it_always_returns_the_time_it_was_given(): void
    {
        $clock = FixedClock::at('2026-11-06 10:00:00');

        self::assertEquals(new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('UTC')), $clock->now());
        self::assertEquals($clock->now(), $clock->now());
    }

    #[Test]
    public function it_can_be_moved_forward(): void
    {
        $clock = FixedClock::at('2026-11-06 10:00:00');

        $clock->advance(new \DateInterval('PT90M'));

        self::assertEquals(new \DateTimeImmutable('2026-11-06 11:30:00', new \DateTimeZone('UTC')), $clock->now());
    }
}
