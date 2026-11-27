<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Console;

use App\Catalog\Application\ReindexProductSearch\ReindexProductSearchHandler;
use App\Catalog\Infrastructure\Console\ReindexProductSearchConsoleCommand;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Tests\Support\Catalog\InMemoryProductSearchIndex;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Against the Typesense service of the stack, on the "products_test" alias of the test environment.
 */
final class ReindexProductSearchConsoleCommandTest extends KernelTestCase
{
    #[Test]
    public function it_rebuilds_the_index_and_prints_how_many_products_it_indexed(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('DELETE FROM price_snapshot');
        $connection->executeStatement('DELETE FROM product');
        foreach ([1, 2] as $number) {
            $connection->insert('product', [
                'id' => BinaryUuid::toDatabase(\sprintf('00000000-0000-4000-8000-%012d', $number)),
                'supermarket_id' => 'mercadona',
                'external_id' => (string) $number,
                'name' => 'Leche '.$number,
                'price_cents' => 100,
                'available' => true,
                'last_seen_at' => '2026-11-08 06:00:00',
            ], ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN]);
        }

        $command = new CommandTester(new Application(self::$kernel ?? throw new \LogicException())->find('app:catalog:reindex-search'));
        $exitCode = $command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode, $command->getDisplay());
        self::assertStringContainsString('Indexed 2 products.', $command->getDisplay());
    }

    #[Test]
    public function if_empty_it_leaves_an_index_with_products_as_it_is(): void
    {
        $index = new InMemoryProductSearchIndex(products: 5);
        $index->rebuild();

        $command = self::tester($index);
        $exitCode = $command->execute(['--if-empty' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, $index->rebuilds);
        self::assertStringContainsString('The search index already has products', $command->getDisplay());
    }

    #[Test]
    public function if_empty_it_builds_a_missing_index(): void
    {
        $index = new InMemoryProductSearchIndex(products: 5);

        $command = self::tester($index);
        $exitCode = $command->execute(['--if-empty' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Indexed 5 products.', $command->getDisplay());
    }

    #[Test]
    public function it_exits_with_1_when_typesense_fails(): void
    {
        $command = self::tester(new InMemoryProductSearchIndex(failing: true));

        $exitCode = $command->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('the fake index is failing', $command->getDisplay());
    }

    private static function tester(InMemoryProductSearchIndex $index): CommandTester
    {
        $application = new \Symfony\Component\Console\Application();
        $application->addCommand(new ReindexProductSearchConsoleCommand(new ReindexProductSearchHandler($index)));

        return new CommandTester($application->find('app:catalog:reindex-search'));
    }
}
