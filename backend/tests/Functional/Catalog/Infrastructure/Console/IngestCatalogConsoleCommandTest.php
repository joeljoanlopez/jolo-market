<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Console;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Infrastructure\Search\TypesenseClient;
use App\Tests\Support\Catalog\ConfigurableCatalogSource;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The test environment enables "mercadona" and "aldi" (backend/.env.test) and replaces
 * their sources with ConfigurableCatalogSource. No network is involved.
 */
final class IngestCatalogConsoleCommandTest extends KernelTestCase
{
    private Connection $connection;
    private CommandTester $command;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM price_snapshot');
        $this->connection->executeStatement('DELETE FROM product');
        $this->connection->executeStatement('DELETE FROM ingestion_run');

        $this->command = new CommandTester(new Application($kernel)->find('app:catalog:ingest'));
    }

    #[Test]
    public function it_ingests_every_enabled_supermarket(): void
    {
        $this->source('mercadona')->willReturn([new SourceProduct('1', 'Leche', 99, '1 L')]);
        $this->source('aldi')->willReturn([new SourceProduct('1', 'Pan', 50, '1 ud'), new SourceProduct('2', 'Sal', 30, '1 kg')]);

        $exitCode = $this->command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(3, $this->countRows('product'));
        self::assertSame(3, $this->countRows('price_snapshot'));
        self::assertSame(2, $this->countRows("ingestion_run WHERE status = 'succeeded'"));
    }

    #[Test]
    public function it_ingests_only_the_supermarket_given_as_option(): void
    {
        $this->source('mercadona')->willReturn([new SourceProduct('1', 'Leche', 99, '1 L')]);
        $this->source('aldi')->willReturn([new SourceProduct('1', 'Pan', 50, '1 ud')]);

        $exitCode = $this->command->execute(['--supermarket' => 'aldi']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(['aldi'], $this->connection->fetchFirstColumn('SELECT supermarket_id FROM ingestion_run'));
    }

    #[Test]
    public function it_prints_a_summary_of_every_run(): void
    {
        $this->source('mercadona')->willReturn([new SourceProduct('1', 'Leche', 99, '1 L'), new SourceProduct('2', ' ', 10)]);
        $this->source('aldi')->willFail(new \RuntimeException('HTTP 503 from the store.'));

        $this->command->execute([]);

        $output = $this->command->getDisplay();
        self::assertMatchesRegularExpression('/mercadona\s+succeeded\s+1\s+1\s+0/', $output);
        self::assertMatchesRegularExpression('/aldi\s+failed\s+0\s+0\s+0/', $output);
        self::assertStringContainsString('HTTP 503 from the store.', $output);
    }

    #[Test]
    public function it_fails_when_any_supermarket_fails(): void
    {
        $this->source('mercadona')->willReturn([new SourceProduct('1', 'Leche', 99, '1 L')]);
        $this->source('aldi')->willFail(new \RuntimeException('Boom.'));

        $exitCode = $this->command->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(1, $this->countRows('product'));
    }

    #[Test]
    public function it_rebuilds_the_search_index_after_ingesting(): void
    {
        $this->source('mercadona')->willReturn([new SourceProduct('1', 'Leche', 99, '1 L')]);
        $this->source('aldi')->willFail(new \RuntimeException('Boom.'));

        $this->command->execute([]);

        $typesense = self::getContainer()->get('test.catalog.typesense_client');
        self::assertInstanceOf(TypesenseClient::class, $typesense);
        $alias = $typesense->request('GET', '/aliases/products_test');
        self::assertIsString($alias['collection_name']);
        $documents = $typesense->exportDocuments($alias['collection_name']);
        self::assertCount(1, $documents);
        self::assertSame('Leche', $documents[0]['name']);
    }

    #[Test]
    public function it_fails_but_keeps_the_catalog_when_the_search_index_cannot_be_rebuilt(): void
    {
        self::getContainer()->set(TypesenseClient::class, new TypesenseClient(HttpClient::create(), 'http://127.0.0.1:1', 'key'));
        $this->command = new CommandTester(new Application(self::$kernel ?? throw new \LogicException())->find('app:catalog:ingest'));
        $this->source('mercadona')->willReturn([new SourceProduct('1', 'Leche', 99, '1 L')]);
        $this->source('aldi')->willReturn([new SourceProduct('1', 'Pan', 50, '1 ud')]);

        $exitCode = $this->command->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('The search index could not be rebuilt', $this->command->getDisplay());
        self::assertSame(2, $this->countRows('product'));
        self::assertSame(2, $this->countRows("ingestion_run WHERE status = 'succeeded'"));
    }

    #[Test]
    public function it_fails_without_ingesting_anything_for_a_supermarket_that_is_not_enabled(): void
    {
        $exitCode = $this->command->execute(['--supermarket' => 'bonpreu']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Supermarket "bonpreu" is not enabled', $this->command->getDisplay());
        self::assertSame(0, $this->countRows('ingestion_run'));
    }

    private function source(string $supermarket): ConfigurableCatalogSource
    {
        $source = self::getContainer()->get('test.catalog_source.'.$supermarket);
        self::assertInstanceOf(ConfigurableCatalogSource::class, $source);

        return $source;
    }

    private function countRows(string $from): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM '.$from);
        self::assertIsNumeric($count);

        return (int) $count;
    }
}
