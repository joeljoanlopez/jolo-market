<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Dev;

use App\Shared\Domain\Clock;
use App\Tests\Support\Dev\DevEnvironment;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PD-55: one dummy run per chain and day, with the clock on that day.
 */
final class BackfillCatalogConsoleCommandTest extends KernelTestCase
{
    use DevEnvironment;

    private Connection $connection;
    private CommandTester $command;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->setUpDevEnvironment();
        $kernel = self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM list_item');
        $this->connection->executeStatement('DELETE FROM price_snapshot');
        $this->connection->executeStatement('DELETE FROM product');
        $this->connection->executeStatement('DELETE FROM ingestion_run');
        $clock = self::getContainer()->get(Clock::class);
        self::assertInstanceOf(Clock::class, $clock);
        $this->now = $clock->now();

        $this->command = new CommandTester(new Application($kernel)->find('app:dev:catalog:backfill'));
    }

    #[Test]
    public function it_leaves_one_run_per_chain_for_each_of_the_last_days(): void
    {
        $exitCode = $this->command->execute(['--days' => '3']);

        self::assertSame(Command::SUCCESS, $exitCode, $this->command->getDisplay());
        foreach (['mercadona', 'bonpreu', 'aldi'] as $chain) {
            self::assertSame(
                $this->lastDays(3),
                $this->connection->fetchFirstColumn(
                    "SELECT DATE(started_at) FROM ingestion_run WHERE supermarket_id = ? AND status = 'succeeded' ORDER BY started_at",
                    [$chain],
                ),
            );
        }
    }

    #[Test]
    public function it_dates_the_snapshots_of_each_run_on_its_day(): void
    {
        $this->command->execute(['--days' => '3']);

        $snapshotDays = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT DATE(s.captured_at) AS day FROM price_snapshot s ORDER BY day',
        );
        self::assertSame($this->lastDays(3), $snapshotDays);
        $milkSnapshots = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM price_snapshot s JOIN product p ON p.id = s.product_id
             WHERE p.supermarket_id = 'mercadona' AND p.name = 'Leche entera'",
        );
        self::assertSame(3, self::integer($milkSnapshots));
    }

    #[Test]
    public function running_it_again_adds_nothing(): void
    {
        $this->command->execute(['--days' => '3']);
        $runs = $this->rowsIn('ingestion_run');
        $snapshots = $this->rowsIn('price_snapshot');

        $exitCode = $this->command->execute(['--days' => '3']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame($runs, $this->rowsIn('ingestion_run'));
        self::assertSame($snapshots, $this->rowsIn('price_snapshot'));
    }

    #[Test]
    public function it_fills_only_the_days_without_a_run(): void
    {
        $this->command->execute(['--days' => '2']);

        $this->command->execute(['--days' => '4']);

        self::assertSame(12, $this->rowsIn('ingestion_run'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDays(): iterable
    {
        yield 'zero' => ['0'];
        yield 'more than two years' => ['731'];
        yield 'not a number' => ['many'];
    }

    #[Test]
    #[DataProvider('invalidDays')]
    public function it_rejects_a_number_of_days_outside_1_to_730_and_ingests_nothing(string $days): void
    {
        $exitCode = $this->command->execute(['--days' => $days]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('--days must be a whole number from 1 to 730', $this->command->getDisplay());
        self::assertSame(0, $this->rowsIn('ingestion_run'));
    }

    /**
     * @return list<string> the last $days days up to today, oldest first, as Y-m-d in UTC
     */
    private function lastDays(int $days): array
    {
        $dates = [];
        for ($offset = $days - 1; $offset >= 0; --$offset) {
            $dates[] = $this->now->setTimezone(new \DateTimeZone('UTC'))->modify(\sprintf('-%d days', $offset))->format('Y-m-d');
        }

        return $dates;
    }

    private function rowsIn(string $table): int
    {
        return self::integer($this->connection->fetchOne('SELECT COUNT(*) FROM '.$table));
    }

    private static function integer(mixed $count): int
    {
        self::assertIsNumeric($count);

        return (int) $count;
    }
}
