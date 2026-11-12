<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Console;

use App\Catalog\Application\PrunePriceHistory\PrunePriceHistoryHandler;
use App\Catalog\Infrastructure\Console\PrunePriceHistoryConsoleCommand;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The test environment keeps the default retention of 24 months (backend/.env). The real clock is used, so the
 * snapshots are years away from the cutoff.
 */
final class PrunePriceHistoryConsoleCommandTest extends KernelTestCase
{
    private const string MILK = '00000000-0000-4000-8000-000000000001';
    private const string BREAD = '00000000-0000-4000-8000-000000000002';

    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM price_snapshot');
        $this->connection->executeStatement('DELETE FROM product');
    }

    #[Test]
    public function it_deletes_old_snapshots_and_keeps_the_latest_of_each_product(): void
    {
        $this->product(self::MILK, '1');
        $this->product(self::BREAD, '2');
        $now = new \DateTimeImmutable();
        $this->snapshot(self::MILK, new \DateTimeImmutable('2000-01-01 05:00:00'));
        $this->snapshot(self::MILK, $now->modify('-1 day'));
        $this->snapshot(self::BREAD, new \DateTimeImmutable('2000-01-01 05:00:00'));
        $this->snapshot(self::BREAD, new \DateTimeImmutable('2000-01-02 05:00:00'));

        $command = new CommandTester(new Application(self::$kernel ?? throw new \LogicException())->find('app:catalog:prune-history'));
        $exitCode = $command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(2, $this->countSnapshots());
        self::assertStringContainsString('Deleted 2 price snapshots', $command->getDisplay());
        self::assertStringContainsString('24 months', $command->getDisplay());
    }

    #[Test]
    public function it_fails_with_invalid_configuration_for_a_retention_below_one_month(): void
    {
        $handler = self::getContainer()->get('test.catalog.prune_price_history_handler');
        self::assertInstanceOf(PrunePriceHistoryHandler::class, $handler);
        $application = new \Symfony\Component\Console\Application();
        $application->addCommand(new PrunePriceHistoryConsoleCommand($handler, 0));

        $command = new CommandTester($application->find('app:catalog:prune-history'));
        $exitCode = $command->execute([]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('at least 1 month', $command->getDisplay());
    }

    private function product(string $id, string $externalId): void
    {
        $this->connection->insert('product', [
            'id' => BinaryUuid::toDatabase($id), 'supermarket_id' => 'mercadona', 'external_id' => $externalId,
            'name' => 'Producto', 'price_cents' => 100, 'available' => true, 'last_seen_at' => '2026-11-08 06:00:00',
        ], ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN]);
    }

    private function snapshot(string $productId, \DateTimeImmutable $capturedAt): void
    {
        $this->connection->insert('price_snapshot', [
            'product_id' => BinaryUuid::toDatabase($productId), 'price_cents' => 100, 'captured_at' => DbalDateTime::toDatabase($capturedAt),
        ], ['product_id' => ParameterType::BINARY]);
    }

    private function countSnapshots(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM price_snapshot');
        self::assertIsNumeric($count);

        return (int) $count;
    }
}
