<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Domain\Model\PriceSnapshot;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\ProductDetails;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalPriceSnapshotRepository;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalProductRepository;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;

final class DbalPriceSnapshotRepositoryTest extends DbalTestCase
{
    private DbalPriceSnapshotRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DbalPriceSnapshotRepository($this->connection);
    }

    #[Test]
    public function it_appends_a_batch_of_snapshots(): void
    {
        $milk = $this->storedProduct('1', Packaging::grams('500'));
        $bread = $this->storedProduct('2', null);
        $capturedAt = new \DateTimeImmutable('2026-11-08 06:00:00.5', new \DateTimeZone('UTC'));

        $this->repository->append($milk->captureSnapshot($capturedAt), $bread->captureSnapshot($capturedAt));

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(product_id)) AS product_id, price_cents, unit_price_cents, unit_price_unit, captured_at
             FROM price_snapshot ORDER BY price_cents',
        );
        self::assertSame([
            ['product_id' => '00000000000040008000000000000001', 'price_cents' => 125, 'unit_price_cents' => 250, 'unit_price_unit' => 'kg', 'captured_at' => '2026-11-08 06:00:00.500000'],
            ['product_id' => '00000000000040008000000000000002', 'price_cents' => 130, 'unit_price_cents' => null, 'unit_price_unit' => null, 'captured_at' => '2026-11-08 06:00:00.500000'],
        ], $rows);
    }

    #[Test]
    public function it_appends_a_large_batch(): void
    {
        $product = $this->storedProduct('1', null);
        $snapshots = array_map(
            static fn (int $minute): PriceSnapshot => $product->captureSnapshot(new \DateTimeImmutable('2026-11-08 00:00')->modify("+{$minute} minutes")),
            range(1, 1000),
        );

        $this->repository->append(...$snapshots);

        self::assertSame(1000, $this->countRows('price_snapshot'));
    }

    #[Test]
    public function it_does_nothing_when_there_are_no_snapshots(): void
    {
        $this->repository->append();

        self::assertSame(0, $this->countRows('price_snapshot'));
    }

    #[Test]
    public function it_deletes_the_snapshots_captured_before_the_cutoff(): void
    {
        $milk = $this->storedProduct('1', null);
        $this->repository->append(
            $milk->captureSnapshot(self::utc('2024-11-12 04:59:59.999999')),
            $milk->captureSnapshot(self::utc('2024-11-12 05:00:00')),
            $milk->captureSnapshot(self::utc('2026-11-12 05:00:00')),
        );

        $deleted = $this->repository->deleteCapturedBefore(self::utc('2024-11-12 05:00:00'));

        self::assertSame(1, $deleted);
        self::assertSame(['2024-11-12 05:00:00.000000', '2026-11-12 05:00:00.000000'], $this->capturedAt('1'));
    }

    #[Test]
    public function it_keeps_the_latest_snapshot_of_each_product_however_old(): void
    {
        $milk = $this->storedProduct('1', null);
        $bread = $this->storedProduct('2', null);
        $this->repository->append(
            $milk->captureSnapshot(self::utc('2023-06-01 05:00:00')),
            $milk->captureSnapshot(self::utc('2023-01-01 05:00:00')),
            $bread->captureSnapshot(self::utc('2023-02-01 05:00:00')),
            $bread->captureSnapshot(self::utc('2026-11-01 05:00:00')),
        );

        $deleted = $this->repository->deleteCapturedBefore(self::utc('2024-11-12 05:00:00'));

        self::assertSame(2, $deleted);
        self::assertSame(['2023-06-01 05:00:00.000000'], $this->capturedAt('1'));
        self::assertSame(['2026-11-01 05:00:00.000000'], $this->capturedAt('2'));
    }

    #[Test]
    public function it_deletes_nothing_when_every_snapshot_is_recent(): void
    {
        $milk = $this->storedProduct('1', null);
        $this->repository->append($milk->captureSnapshot(self::utc('2026-11-01 05:00:00')));

        self::assertSame(0, $this->repository->deleteCapturedBefore(self::utc('2024-11-12 05:00:00')));
        self::assertSame(1, $this->countRows('price_snapshot'));
    }

    /**
     * @return list<string>
     */
    private function capturedAt(string $id): array
    {
        $capturedAt = $this->connection->fetchFirstColumn(
            "SELECT captured_at FROM price_snapshot WHERE LOWER(HEX(product_id)) = CONCAT('0000000000004000800000000000000', ?) ORDER BY captured_at",
            [$id],
        );

        return array_map(static fn (mixed $value): string => \is_string($value) ? $value : throw new \UnexpectedValueException(), $capturedAt);
    }

    private static function utc(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
    }

    private function storedProduct(string $id, ?Packaging $packaging): Product
    {
        $product = Product::register(
            ProductId::fromString('00000000-0000-4000-8000-00000000000'.$id),
            SupermarketId::fromString('mercadona'),
            ExternalProductId::fromString($id),
            ProductDetails::of(ProductName::fromString('Producto '.$id), null, null, null),
            Money::eur(null === $packaging ? 130 : 125),
            $packaging,
            new \DateTimeImmutable('2026-11-08 06:00:00'),
        );
        new DbalProductRepository($this->connection)->save($product);

        return $product;
    }
}
