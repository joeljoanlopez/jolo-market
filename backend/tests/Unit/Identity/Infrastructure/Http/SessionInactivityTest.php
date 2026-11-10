<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Http;

use App\Identity\Infrastructure\Http\SessionInactivity;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionInactivityTest extends TestCase
{
    private const string NOW = '2026-11-20 12:00:00';

    #[Test]
    public function a_session_used_less_than_seven_days_ago_is_still_valid(): void
    {
        self::assertFalse($this->inactivity()->hasExpired(self::timestamp('2026-11-13 12:00:01')));
    }

    #[Test]
    public function a_session_unused_for_seven_days_has_expired(): void
    {
        self::assertTrue($this->inactivity()->hasExpired(self::timestamp('2026-11-13 12:00:00')));
    }

    #[Test]
    public function a_new_session_has_not_expired(): void
    {
        self::assertFalse($this->inactivity()->hasExpired(null));
    }

    #[Test]
    public function the_cookie_is_renewed_at_most_once_an_hour(): void
    {
        self::assertTrue($this->inactivity()->cookieNeedsRenewal(null));
        self::assertFalse($this->inactivity()->cookieNeedsRenewal(self::timestamp('2026-11-20 11:00:01')));
        self::assertTrue($this->inactivity()->cookieNeedsRenewal(self::timestamp('2026-11-20 11:00:00')));
    }

    private function inactivity(): SessionInactivity
    {
        return new SessionInactivity(FixedClock::at(self::NOW));
    }

    private static function timestamp(string $utc): int
    {
        return new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))->getTimestamp();
    }
}
