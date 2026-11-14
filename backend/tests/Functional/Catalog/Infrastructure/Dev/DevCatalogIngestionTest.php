<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Dev;

use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Infrastructure\Source\Aldi\AldiCatalogSource;
use App\Catalog\Infrastructure\Source\BonpreuEsclat\BonpreuEsclatCatalogSource;
use App\Catalog\Infrastructure\Source\Dummy\DummyCatalogSource;
use App\Catalog\Infrastructure\Source\Mercadona\MercadonaCatalogSource;
use App\Tests\Support\Dev\DevEnvironment;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PD-53: in the dev environment ingestion uses the dummy sources unless CATALOG_SOURCES=real.
 * No test here makes a request: the real adapters are only built, never called.
 */
final class DevCatalogIngestionTest extends KernelTestCase
{
    use DevEnvironment;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->setUpDevEnvironment();
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM list_item');
        $this->connection->executeStatement('DELETE FROM price_snapshot');
        $this->connection->executeStatement('DELETE FROM product');
        $this->connection->executeStatement('DELETE FROM ingestion_run');
    }

    #[Test]
    public function the_ingest_command_imports_the_dummy_catalog_of_the_three_chains(): void
    {
        $command = new CommandTester(new Application(self::$kernel ?? self::fail('No kernel.'))->find('app:catalog:ingest'));

        $exitCode = $command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode, $command->getDisplay());
        $runs = $this->connection->fetchAllKeyValue("SELECT supermarket_id, products_skipped FROM ingestion_run WHERE status = 'succeeded' ORDER BY supermarket_id");
        self::assertSame(['aldi' => 1, 'bonpreu' => 1, 'mercadona' => 1], $runs);
        $products = $this->connection->fetchOne("SELECT COUNT(*) FROM product WHERE external_id LIKE 'dummy-%'");
        self::assertIsNumeric($products);
        self::assertGreaterThanOrEqual(90, (int) $products);
    }

    #[Test]
    public function ingestion_uses_the_dummy_sources_by_default(): void
    {
        self::assertSame(
            [DummyCatalogSource::class, DummyCatalogSource::class, DummyCatalogSource::class],
            $this->sourceClasses(),
        );
    }

    #[Test]
    public function ingestion_uses_the_real_adapters_with_catalog_sources_real(): void
    {
        $this->setVariable('CATALOG_SOURCES', 'real');
        self::ensureKernelShutdown();
        self::bootKernel();

        $classes = $this->sourceClasses();
        sort($classes);
        self::assertSame([AldiCatalogSource::class, BonpreuEsclatCatalogSource::class, MercadonaCatalogSource::class], $classes);
    }

    /**
     * @return list<class-string>
     */
    private function sourceClasses(): array
    {
        $sources = self::getContainer()->get('catalog.dev.sources');
        self::assertInstanceOf(\Traversable::class, $sources);

        $classes = [];
        foreach ($sources as $source) {
            self::assertInstanceOf(SupermarketCatalogSource::class, $source);
            $classes[] = $source::class;
        }

        return $classes;
    }
}
