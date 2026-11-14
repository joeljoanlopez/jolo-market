<?php

declare(strict_types=1);

namespace App\Tests\Integration\ShoppingList\Infrastructure\Dev;

use App\Tests\Support\Dev\DevEnvironment;
use App\Tests\Support\Dev\DevTables;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PD-57: the lists reset empties only the tables of the ShoppingList context.
 */
final class ResetShoppingListsConsoleCommandTest extends KernelTestCase
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
    public function it_empties_lists_members_and_items_only(): void
    {
        new CommandTester($this->application->find('app:dev:catalog:backfill'))->execute(['--days' => '1']);
        $this->tables->storeUserAndList();

        $exitCode = new CommandTester($this->application->find('app:dev:lists:reset'))->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(0, $this->tables->rows('shopping_list'));
        self::assertSame(0, $this->tables->rows('list_member'));
        self::assertSame(0, $this->tables->rows('list_item'));
        self::assertSame(1, $this->tables->rows('user'));
        self::assertSame(3, $this->tables->rows('ingestion_run'));
    }

    #[Test]
    public function without_interaction_it_refuses_unless_forced(): void
    {
        $this->tables->storeUserAndList();

        $exitCode = new CommandTester($this->application->find('app:dev:lists:reset'))->execute([], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(1, $this->tables->rows('shopping_list'));
    }
}
