<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Dev;

use App\Tests\Support\Dev\DevEnvironment;
use App\Tests\Support\Dev\DevTables;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PD-57: the catalog reset empties only the tables of the Catalog context.
 */
final class ResetCatalogConsoleCommandTest extends KernelTestCase
{
    use DevEnvironment;

    private DevTables $tables;
    private Application $application;

    protected function setUp(): void
    {
        $this->setUpDevEnvironment();
        $this->application = new Application(self::bootKernel());
        $this->tables = DevTables::of(self::getContainer());
        $this->tables->emptyAll();
    }

    #[Test]
    public function it_empties_products_snapshots_and_ingestion_runs_only(): void
    {
        new CommandTester($this->application->find('app:dev:catalog:backfill'))->execute(['--days' => '2']);
        $this->tables->storeUserAndList();

        $exitCode = new CommandTester($this->application->find('app:dev:catalog:reset'))->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(0, $this->tables->rows('product'));
        self::assertSame(0, $this->tables->rows('price_snapshot'));
        self::assertSame(0, $this->tables->rows('ingestion_run'));
        self::assertSame(1, $this->tables->rows('user'));
        self::assertSame(1, $this->tables->rows('shopping_list'));
        self::assertSame(3, $this->tables->rows('supermarket'));
    }

    #[Test]
    public function without_interaction_it_refuses_unless_forced(): void
    {
        new CommandTester($this->application->find('app:dev:catalog:backfill'))->execute(['--days' => '1']);
        $command = new CommandTester($this->application->find('app:dev:catalog:reset'));

        $exitCode = $command->execute([], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(3, $this->tables->rows('ingestion_run'));
    }
}
