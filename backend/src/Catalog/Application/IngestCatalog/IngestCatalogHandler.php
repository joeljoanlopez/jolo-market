<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

use App\Catalog\Domain\Model\PriceSnapshot;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Port\PriceSnapshotRepository;
use App\Catalog\Domain\Port\ProductRepository;
use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Domain\Service\PackagingParser;
use App\Catalog\Domain\ValueObject\CategoryPath;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\ProductDetails;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Domain\ValueObject\Url;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Exception\InvalidArgument;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\Money;

/**
 * Imports the catalog of every enabled supermarket (or only one) for the instance postal code.
 *
 * Each supermarket runs on its own: a failure is recorded in its run and the next one starts.
 * See PD-22 to PD-25 for unavailable, malformed, enabled and failed-run rules.
 */
final readonly class IngestCatalogHandler
{
    private const int SNAPSHOT_BATCH_SIZE = 500;

    /** @var array<string, SupermarketCatalogSource> keyed by supermarket slug */
    private array $sources;

    private PackagingParser $packagingParser;

    /**
     * @param iterable<SupermarketCatalogSource> $sources one per supermarket chain
     */
    public function __construct(
        private IngestionSettings $settings,
        iterable $sources,
        private ProductRepository $products,
        private PriceSnapshotRepository $snapshots,
        private IngestionRunRepository $runs,
        private Clock $clock,
        private IdGenerator $ids,
        private IngestionLogger $logger,
    ) {
        $bySlug = [];
        foreach ($sources as $source) {
            $bySlug[$source->supermarketId()->value()] = $source;
        }
        $this->sources = $bySlug;
        $this->packagingParser = new PackagingParser();
    }

    /**
     * @throws InvalidArgument when the configuration or the requested supermarket is invalid; nothing is ingested then
     */
    public function __invoke(IngestCatalogCommand $command): IngestCatalogResult
    {
        $postalCode = $this->settings->postalCode();

        $summaries = [];
        foreach ($this->supermarketsToIngest($command) as $supermarketId) {
            $summaries[] = IngestionRunSummary::of($this->ingest($supermarketId, $postalCode));
        }

        return new IngestCatalogResult($summaries);
    }

    /**
     * @return list<SupermarketId>
     */
    private function supermarketsToIngest(IngestCatalogCommand $command): array
    {
        $enabled = $this->settings->enabledSupermarkets();

        if (null === $command->supermarket) {
            return $enabled;
        }

        foreach ($enabled as $supermarketId) {
            if ($supermarketId->value() === $command->supermarket) {
                return [$supermarketId];
            }
        }

        throw SupermarketNotEnabled::named($command->supermarket);
    }

    private function ingest(SupermarketId $supermarketId, PostalCode $postalCode): IngestionRun
    {
        $run = IngestionRun::start($this->ids->generate(), $supermarketId, $this->clock->now());
        $this->runs->save($run);

        $seen = [];
        $skipped = 0;
        $pendingSnapshots = [];

        try {
            $source = $this->sources[$supermarketId->value()]
                ?? throw new \RuntimeException(\sprintf('No catalog source is configured for supermarket "%s".', $supermarketId->value()));

            foreach ($source->fetchCatalog($postalCode) as $sourceProduct) {
                // A product listed in several categories is still one product: see it once.
                if (isset($seen[$sourceProduct->externalId])) {
                    continue;
                }

                try {
                    $product = $this->upsert($supermarketId, $sourceProduct);
                } catch (InvalidArgument $malformed) {
                    ++$skipped;
                    $this->logger->productSkipped($supermarketId, $sourceProduct->externalId, $malformed->getMessage());
                    continue;
                }

                $seen[$sourceProduct->externalId] = true;
                $pendingSnapshots[] = $product->captureSnapshot($product->lastSeenAt());
                if (\count($pendingSnapshots) >= self::SNAPSHOT_BATCH_SIZE) {
                    $this->flush($pendingSnapshots);
                }
            }

            if ([] === $seen) {
                throw new \RuntimeException('The catalog returned no valid products.');
            }

            $this->flush($pendingSnapshots);
            $markedUnavailable = $this->markNotSeenAsUnavailable($supermarketId, $run->startedAt());
            $run->succeed($this->clock->now(), \count($seen), $skipped, $markedUnavailable);
        } catch (\Throwable $failure) {
            // What was imported before the failure is kept (PD-25), with its snapshots.
            $this->flush($pendingSnapshots);
            $run->fail($this->clock->now(), \count($seen), $skipped, $failure->getMessage());
        }

        $this->runs->save($run);
        $this->logger->runFinished($run);

        return $run;
    }

    /**
     * @throws InvalidArgument when the source product is malformed (PD-23)
     */
    private function upsert(SupermarketId $supermarketId, SourceProduct $source): Product
    {
        $externalId = ExternalProductId::fromString($source->externalId);
        $details = ProductDetails::of(
            ProductName::fromString($source->name),
            $source->brand,
            [] === $source->categoryPath ? null : CategoryPath::fromSegments($source->categoryPath),
            null === $source->imageUrl ? null : Url::fromString($source->imageUrl),
        );
        $price = Money::eur($source->priceCents);
        $packaging = null === $source->packaging ? null : $this->packagingParser->parse($source->packaging);
        $seenAt = $this->clock->now();

        $product = $this->products->findBySupermarketAndExternalId($supermarketId, $externalId);
        if (null === $product) {
            $product = Product::register(
                ProductId::fromString($this->ids->generate()->value()),
                $supermarketId,
                $externalId,
                $details,
                $price,
                $packaging,
                $seenAt,
            );
        } else {
            $product->updateFromSource($details, $price, $packaging, $seenAt);
        }

        $this->products->save($product);

        return $product;
    }

    private function markNotSeenAsUnavailable(SupermarketId $supermarketId, \DateTimeImmutable $runStartedAt): int
    {
        $marked = 0;
        foreach ($this->products->findAvailableNotSeenSince($supermarketId, $runStartedAt) as $product) {
            $product->markUnavailable();
            $this->products->save($product);
            ++$marked;
        }

        return $marked;
    }

    /**
     * @param list<PriceSnapshot> $snapshots emptied once appended
     */
    private function flush(array &$snapshots): void
    {
        if ([] === $snapshots) {
            return;
        }

        $this->snapshots->append(...$snapshots);
        $snapshots = [];
    }
}
