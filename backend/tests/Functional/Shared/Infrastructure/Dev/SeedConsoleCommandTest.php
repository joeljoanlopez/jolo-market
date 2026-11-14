<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Infrastructure\Dev;

use App\Tests\Support\Dev\DevEnvironment;
use App\Tests\Support\Dev\DevTables;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PD-57: one command loads the development data, and --reset starts again from it.
 */
final class SeedConsoleCommandTest extends KernelTestCase
{
    use DevEnvironment;

    private const int DAYS = 180;
    private const int CHAINS = 3;

    private DevTables $tables;
    private Connection $connection;
    private CommandTester $command;

    protected function setUp(): void
    {
        $this->setUpDevEnvironment();
        $kernel = self::bootKernel();
        $this->tables = DevTables::of(self::getContainer());
        $this->tables->emptyAll();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;

        $this->command = new CommandTester(new Application($kernel)->find('app:dev:seed'));
    }

    /**
     * One test, because a full seed is slow: 180 days of three chains.
     */
    #[Test]
    public function it_loads_the_dummy_users_and_180_days_of_history_never_touching_other_users_and_a_second_call_changes_nothing(): void
    {
        $this->tables->storeUserAndList();
        $maria = $this->connection->fetchAssociative("SELECT * FROM user WHERE username = 'maria'");

        $exitCode = $this->command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode, $this->command->getDisplay());
        self::assertSame(['admin', 'maria', 'usuario'], $this->usernames());
        self::assertSame($maria, $this->connection->fetchAssociative("SELECT * FROM user WHERE username = 'maria'"));
        self::assertSame(1, $this->tables->rows('shopping_list'));
        self::assertSame(self::DAYS * self::CHAINS, $this->tables->rows("ingestion_run WHERE status = 'succeeded'"));
        self::assertSame(self::DAYS, $this->tables->rows('(SELECT DISTINCT DATE(started_at) FROM ingestion_run) AS days'));
        $snapshots = $this->tables->rows('price_snapshot');
        $users = $this->connection->fetchAllAssociative('SELECT * FROM user ORDER BY username');

        $exitCode = $this->command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame($users, $this->connection->fetchAllAssociative('SELECT * FROM user ORDER BY username'));
        self::assertSame(self::DAYS * self::CHAINS, $this->tables->rows('ingestion_run'));
        self::assertSame($snapshots, $this->tables->rows('price_snapshot'));
    }

    #[Test]
    public function with_reset_and_force_it_leaves_only_the_dummy_data(): void
    {
        $this->tables->storeUserAndList();
        $this->tables->storeSetupCode();

        $exitCode = $this->command->execute(['--reset' => true, '--force' => true], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode, $this->command->getDisplay());
        self::assertSame(['admin', 'usuario'], $this->usernames());
        self::assertSame(0, $this->tables->rows('shopping_list'));
        self::assertSame(0, $this->tables->rows('setup_code'));
        self::assertSame(self::DAYS * self::CHAINS, $this->tables->rows('ingestion_run'));
    }

    #[Test]
    public function reset_asks_first_and_does_nothing_when_refused(): void
    {
        $this->tables->storeUserAndList();
        $this->command->setInputs(['no']);

        $exitCode = $this->command->execute(['--reset' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Continue?', $this->command->getDisplay());
        $this->assertNothingChanged();
    }

    #[Test]
    public function reset_without_interaction_refuses_unless_forced(): void
    {
        $this->tables->storeUserAndList();

        $exitCode = $this->command->execute(['--reset' => true], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('--force', $this->command->getDisplay());
        $this->assertNothingChanged();
    }

    private function assertNothingChanged(): void
    {
        self::assertSame(['maria'], $this->usernames());
        self::assertSame(1, $this->tables->rows('shopping_list'));
        self::assertSame(0, $this->tables->rows('ingestion_run'));
    }

    /**
     * @return list<mixed>
     */
    private function usernames(): array
    {
        return $this->connection->fetchFirstColumn('SELECT username FROM user ORDER BY username');
    }
}
