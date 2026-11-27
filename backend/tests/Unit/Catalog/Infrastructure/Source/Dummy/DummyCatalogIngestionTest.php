<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Source\Dummy;

use App\Catalog\Application\IngestCatalog\IngestCatalogCommand;
use App\Catalog\Application\IngestCatalog\IngestCatalogHandler;
use App\Catalog\Application\IngestCatalog\IngestionRunStatus;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\UnitOfMeasure;
use App\Catalog\Infrastructure\Source\Dummy\DummyCatalogSource;
use App\Tests\Support\Catalog\FixedIngestionSettings;
use App\Tests\Support\Catalog\InMemoryIngestionLogger;
use App\Tests\Support\Catalog\InMemoryIngestionRunRepository;
use App\Tests\Support\Catalog\InMemoryPriceSnapshotRepository;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use App\Tests\Support\Catalog\InMemoryProductSearchIndex;
use App\Tests\Support\FixedClock;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The dummy sources go through the real ingestion with in-memory repositories (PD-54).
 */
final class DummyCatalogIngestionTest extends TestCase
{
    private const array CHAINS = ['mercadona', 'bonpreu', 'aldi'];

    private InMemoryProductRepository $products;
    private InMemoryIngestionRunRepository $runs;
    private FixedClock $clock;
    private IngestCatalogHandler $ingest;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->runs = new InMemoryIngestionRunRepository();
        $this->clock = FixedClock::at('2026-11-14 05:00:00');
        $this->ingest = new IngestCatalogHandler(
            new FixedIngestionSettings('08001', self::CHAINS),
            array_map(fn (string $chain): DummyCatalogSource => new DummyCatalogSource($chain, $this->clock), self::CHAINS),
            $this->products,
            new InMemoryPriceSnapshotRepository(),
            $this->runs,
            $this->clock,
            new SequentialIdGenerator(),
            new InMemoryIngestionLogger(),
            new InMemoryProductSearchIndex(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function chains(): iterable
    {
        foreach (self::CHAINS as $chain) {
            yield $chain => [$chain];
        }
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_imports_every_chain_skipping_and_counting_its_malformed_product(string $chain): void
    {
        ($this->ingest)(new IngestCatalogCommand());

        $run = $this->runs->forSupermarket($chain);
        self::assertSame(IngestionRunStatus::SUCCEEDED, $run->status());
        self::assertSame(1, $run->productsSkipped());
        self::assertGreaterThanOrEqual(30, $run->productsSeen());
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_has_products_priced_per_kg_litre_and_unit_in_every_chain(string $chain): void
    {
        ($this->ingest)(new IngestCatalogCommand());

        $units = array_map(
            static fn (Product $product): ?UnitOfMeasure => $product->unitPrice()?->unit(),
            $this->productsOf($chain),
        );
        self::assertContains(UnitOfMeasure::KILOGRAM, $units);
        self::assertContains(UnitOfMeasure::LITRE, $units);
        self::assertContains(UnitOfMeasure::UNIT, $units);
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_has_packs_whose_unit_price_counts_every_item(string $chain): void
    {
        ($this->ingest)(new IngestCatalogCommand());

        $water = $this->named($chain, 'Agua mineral');
        $unitPrice = $water->unitPrice() ?? self::fail('The pack has no unit price.');
        // 6 x 1,5 L = 9 L
        self::assertSame(UnitOfMeasure::LITRE, $unitPrice->unit());
        self::assertSame(intdiv($water->currentPrice()->cents() * 10 + 45, 90), $unitPrice->price()->cents());
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_has_a_product_without_unit_price_in_every_chain(string $chain): void
    {
        ($this->ingest)(new IngestCatalogCommand());

        self::assertNull($this->named($chain, 'Detergente líquido')->unitPrice());
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_prices_an_approximate_size_by_its_reference_price(string $chain): void
    {
        ($this->ingest)(new IngestCatalogCommand());

        $chicken = $this->named($chain, 'Pechuga de pollo fileteada');
        self::assertNull($chicken->packaging());
        $unitPrice = $chicken->unitPrice() ?? self::fail('The approximate product has no unit price.');
        self::assertSame(UnitOfMeasure::KILOGRAM, $unitPrice->unit());
        self::assertGreaterThan($chicken->currentPrice()->cents(), $unitPrice->price()->cents());
    }

    #[Test]
    public function it_sells_the_same_products_in_every_chain_so_they_can_be_compared(): void
    {
        ($this->ingest)(new IngestCatalogCommand());

        foreach (['Leche entera', 'Aceite de oliva virgen extra', 'Huevos frescos L'] as $name) {
            $prices = array_map(fn (string $chain): int => $this->named($chain, $name)->currentPrice()->cents(), self::CHAINS);
            self::assertCount(3, array_unique($prices), \sprintf('"%s" should have a different price in each chain.', $name));
        }
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_returns_the_same_products_and_prices_on_the_same_day(string $chain): void
    {
        $morning = new DummyCatalogSource($chain, FixedClock::at('2026-11-14 00:00:01'));
        $evening = new DummyCatalogSource($chain, FixedClock::at('2026-11-14 23:59:59'));
        $postalCode = PostalCode::fromString('08001');

        self::assertEquals([...$morning->fetchCatalog($postalCode)], [...$evening->fetchCatalog($postalCode)]);
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_changes_some_prices_from_one_day_to_the_next(string $chain): void
    {
        ($this->ingest)(new IngestCatalogCommand());
        $before = $this->pricesOf($chain);

        $this->clock->advance(new \DateInterval('P1D'));
        ($this->ingest)(new IngestCatalogCommand());
        $after = $this->pricesOf($chain);

        $changed = array_keys(array_diff_assoc(array_intersect_key($after, $before), $before));
        self::assertNotEmpty($changed);
        self::assertLessThan(\count($before) / 2, \count($changed), 'Most prices should stay the same.');
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_misses_some_products_on_some_days_and_brings_them_back_later(string $chain): void
    {
        $days = [];
        for ($day = 0; $day < 10; ++$day) {
            ($this->ingest)(new IngestCatalogCommand($chain));
            $days[] = array_map(static fn (Product $product): bool => $product->isAvailable(), $this->availabilityOf($chain));
            $this->clock->advance(new \DateInterval('P1D'));
        }

        $cameBack = [];
        foreach ($days as $number => $availability) {
            foreach ($availability as $externalId => $available) {
                if (!$available && \in_array(true, array_column(\array_slice($days, $number + 1), $externalId), true)) {
                    $cameBack[$externalId] = true;
                }
            }
        }
        self::assertGreaterThanOrEqual(1, \count($cameBack));
        self::assertLessThanOrEqual(2, \count($cameBack));
    }

    /**
     * @return list<Product>
     */
    private function productsOf(string $chain): array
    {
        return array_values(array_filter(
            $this->products->all(),
            static fn (Product $product): bool => $product->supermarketId()->value() === $chain,
        ));
    }

    private function named(string $chain, string $name): Product
    {
        foreach ($this->productsOf($chain) as $product) {
            if ($product->details()->name()->value() === $name) {
                return $product;
            }
        }

        self::fail(\sprintf('"%s" sells no "%s".', $chain, $name));
    }

    /**
     * @return array<string, int> by external id, available products only
     */
    private function pricesOf(string $chain): array
    {
        $prices = [];
        foreach ($this->productsOf($chain) as $product) {
            if ($product->isAvailable()) {
                $prices[$product->externalId()->value()] = $product->currentPrice()->cents();
            }
        }

        return $prices;
    }

    /**
     * @return array<string, Product> by external id
     */
    private function availabilityOf(string $chain): array
    {
        $products = [];
        foreach ($this->productsOf($chain) as $product) {
            $products[$product->externalId()->value()] = $product;
        }

        return $products;
    }
}
