<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Health;

use App\Shared\Application\Health\CheckHealth;
use App\Shared\Application\Health\HealthStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckHealthTest extends TestCase
{
    #[Test]
    public function it_reports_ok_when_the_database_is_reachable(): void
    {
        $checkHealth = new CheckHealth(new FakeDatabaseConnectivity(reachable: true));

        self::assertSame(HealthStatus::Ok, $checkHealth());
    }

    #[Test]
    public function it_reports_unavailable_when_the_database_is_unreachable(): void
    {
        $checkHealth = new CheckHealth(new FakeDatabaseConnectivity(reachable: false));

        self::assertSame(HealthStatus::Unavailable, $checkHealth());
    }
}
