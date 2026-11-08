<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\IngestCatalog;

use App\Catalog\Application\IngestCatalog\IngestCatalogCommand;
use App\Catalog\Application\IngestCatalog\IngestCatalogHandler;
use App\Catalog\Application\IngestCatalog\IngestCatalogResult;
use App\Catalog\Application\IngestCatalog\IngestionRunStatus;
use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Domain\ValueObject\UnitOfMeasure;
use App\Catalog\Domain\ValueObject\UnitPrice;
use App\Shared\Domain\Exception\InvalidArgument;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Support\Catalog\FakeCatalogSource;
use App\Tests\Support\Catalog\FixedIngestionSettings;
use App\Tests\Support\Catalog\InMemoryIngestionRunRepository;
use App\Tests\Support\Catalog\InMemoryPriceSnapshotRepository;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use App\Tests\Support\FixedClock;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IngestCatalogHandlerTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemoryPriceSnapshotRepository $snapshots;
    private InMemoryIngestionRunRepository $runs;
    private FixedClock $clock;
    private SequentialIdGenerator $ids;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->snapshots = new InMemoryPriceSnapshotRepository();
        $this->runs = new InMemoryIngestionRunRepository();
        $this->clock = FixedClock::at('2026-11-08 06:00:00');
        $this->ids = new SequentialIdGenerator();
    }

    #[Test]
    public function it_registers_a_product_the_first_time_its_source_returns_it(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [
            new SourceProduct('3400', 'Leche entera', 99, '1 L', 'Hacendado', ['Lácteos', 'Leche'], 'https://example.com/leche.jpg'),
        ])]);

        $product = $this->products->get('mercadona', '3400');
        self::assertSame('Leche entera', $product->details()->name()->value());
        self::assertSame('Hacendado', $product->details()->brand());
        self::assertSame('Lácteos > Leche', $product->details()->category()?->toString());
        self::assertSame('https://example.com/leche.jpg', $product->details()->imageUrl()?->value());
        self::assertSame(99, $product->currentPrice()->cents());
        self::assertTrue(Packaging::litres('1')->equals($product->packaging() ?? self::fail('No packaging.')));
        self::assertEquals(UnitPrice::of(Money::eur(99), UnitOfMeasure::LITRE), $product->unitPrice());
        self::assertEquals($this->clock->now(), $product->lastSeenAt());
    }

    #[Test]
    public function it_updates_a_product_it_already_knows_instead_of_registering_it_again(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('3400', 'Leche', 99, '1 L')])]);
        $this->clock->advance(new \DateInterval('P1D'));

        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('3400', 'Leche entera', 105, '1 L')])]);

        self::assertCount(1, $this->products->all());
        $product = $this->products->get('mercadona', '3400');
        self::assertSame('Leche entera', $product->details()->name()->value());
        self::assertSame(105, $product->currentPrice()->cents());
        self::assertEquals($this->clock->now(), $product->lastSeenAt());
    }

    #[Test]
    public function it_keeps_products_of_different_supermarkets_with_the_same_external_id_apart(): void
    {
        $this->ingest([
            new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')]),
            new FakeCatalogSource('aldi', [new SourceProduct('1', 'Pan', 50, '1 ud')]),
        ]);

        self::assertCount(2, $this->products->all());
    }

    #[Test]
    public function it_appends_one_price_snapshot_per_product_seen(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [
            new SourceProduct('1', 'Leche', 99, '1 L'),
            new SourceProduct('2', 'Arroz', 125, '500 g'),
        ])]);

        $snapshots = $this->snapshots->all();
        self::assertCount(2, $snapshots);
        self::assertSame(125, $snapshots[1]->price()->cents());
        self::assertEquals(UnitPrice::of(Money::eur(250), UnitOfMeasure::KILOGRAM), $snapshots[1]->unitPrice());
        self::assertEquals($this->clock->now(), $snapshots[1]->capturedAt());
        self::assertTrue($this->products->get('mercadona', '2')->id()->equals($snapshots[1]->productId()));
    }

    #[Test]
    public function it_appends_snapshots_in_batches_of_500(): void
    {
        $products = array_map(
            static fn (int $i): SourceProduct => new SourceProduct((string) $i, 'Producto '.$i, 100, '1 kg'),
            range(1, 1001),
        );

        $this->ingest([new FakeCatalogSource('mercadona', $products)]);

        self::assertSame([500, 500, 1], $this->snapshots->batchSizes);
    }

    #[Test]
    public function it_appends_new_snapshots_on_every_run(): void
    {
        $source = new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')]);

        $this->ingest([$source]);
        $this->clock->advance(new \DateInterval('P1D'));
        $this->ingest([$source]);

        self::assertCount(2, $this->snapshots->all());
    }

    #[Test]
    public function it_handles_a_product_returned_twice_in_the_same_run_once(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [
            new SourceProduct('1', 'Leche', 99, '1 L', categoryPath: ['Lácteos']),
            new SourceProduct('1', 'Leche', 99, '1 L', categoryPath: ['Desayuno']),
        ])]);

        self::assertCount(1, $this->snapshots->all());
        self::assertSame(1, $this->runs->single()->productsSeen());
    }

    #[Test]
    public function it_stores_no_unit_price_when_the_packaging_cannot_be_parsed(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [
            new SourceProduct('1', 'Sandía', 300, '3 kg aprox.'),
            new SourceProduct('2', 'Melón', 250),
        ])]);

        self::assertNull($this->products->get('mercadona', '1')->unitPrice());
        self::assertNull($this->products->get('mercadona', '2')->unitPrice());
        self::assertNull($this->snapshots->all()[0]->unitPrice());
    }

    #[Test]
    public function it_marks_the_products_a_successful_run_did_not_see_as_unavailable(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [
            new SourceProduct('1', 'Leche', 99, '1 L'),
            new SourceProduct('2', 'Arroz', 125, '500 g'),
        ])]);
        $this->clock->advance(new \DateInterval('P1D'));

        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')])]);

        self::assertTrue($this->products->get('mercadona', '1')->isAvailable());
        self::assertFalse($this->products->get('mercadona', '2')->isAvailable());
        self::assertSame(1, $this->runs->latest()->productsMarkedUnavailable());
    }

    #[Test]
    public function it_does_not_mark_products_of_other_supermarkets_as_unavailable(): void
    {
        $this->ingest([
            new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')]),
            new FakeCatalogSource('aldi', [new SourceProduct('1', 'Pan', 50, '1 ud')]),
        ]);
        $this->clock->advance(new \DateInterval('P1D'));

        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')])], enabled: ['mercadona']);

        self::assertTrue($this->products->get('aldi', '1')->isAvailable());
    }

    #[Test]
    public function it_skips_and_counts_a_malformed_product_and_imports_the_rest(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [
            new SourceProduct('1', '   ', 99, '1 L'),
            new SourceProduct('2', 'Arroz', -5, '500 g'),
            new SourceProduct('3', 'Pan', 50, '1 ud', imageUrl: 'not a url'),
            new SourceProduct(' ', 'Sal', 30, '1 kg'),
            new SourceProduct('5', 'Leche', 99, '1 L'),
        ])]);

        self::assertCount(1, $this->products->all());
        self::assertCount(1, $this->snapshots->all());
        $run = $this->runs->single();
        self::assertSame(IngestionRunStatus::SUCCEEDED, $run->status());
        self::assertSame(1, $run->productsSeen());
        self::assertSame(4, $run->productsSkipped());
    }

    #[Test]
    public function it_records_a_successful_run_with_its_counts_and_times(): void
    {
        $source = new class($this->clock) implements SupermarketCatalogSource {
            public function __construct(private readonly FixedClock $clock)
            {
            }

            public function supermarketId(): SupermarketId
            {
                return SupermarketId::fromString('mercadona');
            }

            public function fetchCatalog(PostalCode $postalCode): iterable
            {
                yield new SourceProduct('1', 'Leche', 99, '1 L');
                // Fetching the catalog takes five minutes.
                $this->clock->advance(new \DateInterval('PT5M'));
            }
        };

        $this->ingest([$source]);

        $run = $this->runs->single();
        self::assertSame('mercadona', $run->supermarketId()->value());
        self::assertSame(IngestionRunStatus::SUCCEEDED, $run->status());
        self::assertEquals(new \DateTimeImmutable('2026-11-08 06:00:00', new \DateTimeZone('UTC')), $run->startedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-08 06:05:00', new \DateTimeZone('UTC')), $run->finishedAt());
        self::assertSame(1, $run->productsSeen());
        self::assertSame(0, $run->productsSkipped());
        self::assertSame(0, $run->productsMarkedUnavailable());
        self::assertNull($run->error());
    }

    #[Test]
    public function it_saves_the_run_as_running_before_fetching_the_catalog(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')])]);

        self::assertSame(['running', 'succeeded'], $this->runs->savedStatuses);
    }

    #[Test]
    public function it_records_a_failed_run_with_its_error(): void
    {
        $this->ingest([FakeCatalogSource::failing('mercadona', new \RuntimeException('HTTP 503 from the store.'))]);

        $run = $this->runs->single();
        self::assertSame(IngestionRunStatus::FAILED, $run->status());
        self::assertSame('HTTP 503 from the store.', $run->error());
        self::assertNotNull($run->finishedAt());
    }

    #[Test]
    public function it_keeps_ingesting_the_other_supermarkets_when_one_fails(): void
    {
        $this->ingest([
            FakeCatalogSource::failing('mercadona', new \RuntimeException('Boom.')),
            new FakeCatalogSource('aldi', [new SourceProduct('1', 'Pan', 50, '1 ud')]),
        ]);

        self::assertSame(IngestionRunStatus::FAILED, $this->runs->forSupermarket('mercadona')->status());
        self::assertSame(IngestionRunStatus::SUCCEEDED, $this->runs->forSupermarket('aldi')->status());
        self::assertCount(1, $this->products->all());
    }

    #[Test]
    public function it_keeps_what_a_failed_run_imported_before_failing(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')], new \RuntimeException('Boom.'))]);

        self::assertCount(1, $this->products->all());
        self::assertCount(1, $this->snapshots->all());
        self::assertSame(1, $this->runs->single()->productsSeen());
    }

    #[Test]
    public function it_does_not_mark_products_unavailable_when_a_run_fails(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [
            new SourceProduct('1', 'Leche', 99, '1 L'),
            new SourceProduct('2', 'Arroz', 125, '500 g'),
        ])]);
        $this->clock->advance(new \DateInterval('P1D'));

        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')], new \RuntimeException('Boom.'))]);

        self::assertTrue($this->products->get('mercadona', '2')->isAvailable());
    }

    #[Test]
    public function it_records_a_run_that_returns_no_products_as_failed_and_marks_nothing_unavailable(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')])]);
        $this->clock->advance(new \DateInterval('P1D'));

        $this->ingest([new FakeCatalogSource('mercadona', [])]);

        self::assertSame(IngestionRunStatus::FAILED, $this->runs->latest()->status());
        self::assertNotNull($this->runs->latest()->error());
        self::assertTrue($this->products->get('mercadona', '1')->isAvailable());
    }

    #[Test]
    public function it_records_a_run_whose_products_are_all_malformed_as_failed(): void
    {
        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('1', ' ', 99, '1 L')])]);

        self::assertSame(IngestionRunStatus::FAILED, $this->runs->single()->status());
        self::assertSame(1, $this->runs->single()->productsSkipped());
    }

    #[Test]
    public function it_fetches_every_catalog_for_the_instance_postal_code(): void
    {
        $source = new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')]);

        $this->ingest([$source], postalCode: '08001');

        self::assertCount(1, $source->requestedPostalCodes);
        self::assertSame('08001', $source->requestedPostalCodes[0]->value());
    }

    #[Test]
    public function it_ingests_only_the_enabled_supermarkets(): void
    {
        $aldi = new FakeCatalogSource('aldi', [new SourceProduct('1', 'Pan', 50, '1 ud')]);

        $this->ingest([new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')]), $aldi], enabled: ['mercadona']);

        self::assertSame([], $aldi->requestedPostalCodes);
        self::assertCount(1, $this->runs->all());
    }

    #[Test]
    public function it_ingests_only_the_requested_supermarket(): void
    {
        $mercadona = new FakeCatalogSource('mercadona', [new SourceProduct('1', 'Leche', 99, '1 L')]);

        $this->ingest([$mercadona, new FakeCatalogSource('aldi', [new SourceProduct('1', 'Pan', 50, '1 ud')])], only: 'aldi');

        self::assertSame([], $mercadona->requestedPostalCodes);
        self::assertSame('aldi', $this->runs->single()->supermarketId()->value());
    }

    #[Test]
    public function it_rejects_a_requested_supermarket_that_is_not_enabled(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->ingest([new FakeCatalogSource('aldi', [])], enabled: ['mercadona'], only: 'aldi');
    }

    #[Test]
    public function it_records_a_failed_run_for_an_enabled_supermarket_without_a_source(): void
    {
        $this->ingest([new FakeCatalogSource('aldi', [new SourceProduct('1', 'Pan', 50, '1 ud')])], enabled: ['mercadona', 'aldi']);

        self::assertSame(IngestionRunStatus::FAILED, $this->runs->forSupermarket('mercadona')->status());
        self::assertSame(IngestionRunStatus::SUCCEEDED, $this->runs->forSupermarket('aldi')->status());
    }

    #[Test]
    public function it_returns_a_summary_of_every_run(): void
    {
        $result = $this->ingest([
            FakeCatalogSource::failing('mercadona', new \RuntimeException('Boom.')),
            new FakeCatalogSource('aldi', [new SourceProduct('1', 'Pan', 50, '1 ud'), new SourceProduct('2', ' ', 50)]),
        ]);

        self::assertCount(2, $result->runs);
        self::assertSame('mercadona', $result->runs[0]->supermarket);
        self::assertSame('failed', $result->runs[0]->status);
        self::assertSame('Boom.', $result->runs[0]->error);
        self::assertSame('aldi', $result->runs[1]->supermarket);
        self::assertSame('succeeded', $result->runs[1]->status);
        self::assertSame(1, $result->runs[1]->productsSeen);
        self::assertSame(1, $result->runs[1]->productsSkipped);
        self::assertSame(0, $result->runs[1]->productsMarkedUnavailable);
        self::assertFalse($result->allSucceeded());
    }

    /**
     * @param list<SupermarketCatalogSource> $sources
     * @param list<string>|null              $enabled defaults to every source given
     */
    private function ingest(array $sources, ?array $enabled = null, ?string $only = null, string $postalCode = '46001'): IngestCatalogResult
    {
        $enabled ??= array_map(static fn (SupermarketCatalogSource $source): string => $source->supermarketId()->value(), $sources);

        $handler = new IngestCatalogHandler(
            new FixedIngestionSettings($postalCode, $enabled),
            $sources,
            $this->products,
            $this->snapshots,
            $this->runs,
            $this->clock,
            $this->ids,
        );

        return $handler(new IngestCatalogCommand($only));
    }
}
