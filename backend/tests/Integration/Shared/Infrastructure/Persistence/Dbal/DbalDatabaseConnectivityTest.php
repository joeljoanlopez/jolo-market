<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Persistence\Dbal;

use App\Shared\Infrastructure\Persistence\Dbal\DbalDatabaseConnectivity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalDatabaseConnectivityTest extends KernelTestCase
{
    #[Test]
    public function it_reports_the_test_database_as_reachable(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        self::assertTrue((new DbalDatabaseConnectivity($connection))->isReachable());
    }

    #[Test]
    public function it_reports_a_database_that_refuses_connections_as_unreachable(): void
    {
        // Port 1 on loopback refuses immediately, so no network timeout is involved.
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'user' => 'nobody',
            'password' => 'nothing',
            'dbname' => 'missing',
            'serverVersion' => '9.7.4',
        ]);

        self::assertFalse((new DbalDatabaseConnectivity($connection))->isReachable());
    }
}
