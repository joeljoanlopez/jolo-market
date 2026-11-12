<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\GetPriceHistory\PriceHistoryPoint;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalPriceHistoryReadModel;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;

final class DbalPriceHistoryReadModelTest extends DbalTestCase
{
    private const string ID = '00000000-0000-4000-8000-000000000001';
    private const string OTHER_ID = '00000000-0000-4000-8000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        $this->insertProduct(self::ID, '1');
        $this->insertProduct(self::OTHER_ID, '2');
    }

    #[Test]
    public function it_maps_every_field_of_a_snapshot(): void
    {
        $this->insertSnapshot(self::ID, '2026-11-08 05:00:00.123456', 125, 83, 'l');
        $this->insertSnapshot(self::ID, '2026-11-09 05:00:00', 130, null, null);

        self::assertEquals(
            [
                new PriceHistoryPoint(new \DateTimeImmutable('2026-11-08 05:00:00.123456', new \DateTimeZone('UTC')), 125, 83, 'l'),
                new PriceHistoryPoint(new \DateTimeImmutable('2026-11-09 05:00:00', new \DateTimeZone('UTC')), 130, null, null),
            ],
            $this->readModel()->pointsOf(ProductId::fromString(self::ID), null, null),
        );
    }

    #[Test]
    public function it_returns_the_snapshots_oldest_first_and_only_of_the_product(): void
    {
        $this->insertSnapshot(self::ID, '2026-11-10 05:00:00', 130);
        $this->insertSnapshot(self::OTHER_ID, '2026-11-09 05:00:00', 999);
        $this->insertSnapshot(self::ID, '2026-11-08 05:00:00', 125);

        self::assertSame([125, 130], $this->prices($this->readModel()->pointsOf(ProductId::fromString(self::ID), null, null)));
    }

    #[Test]
    public function it_keeps_snapshots_from_the_start_and_before_the_end(): void
    {
        $this->insertSnapshot(self::ID, '2026-11-07 22:59:59.999999', 100);
        $this->insertSnapshot(self::ID, '2026-11-07 23:00:00', 110);
        $this->insertSnapshot(self::ID, '2026-11-08 22:59:59.999999', 120);
        $this->insertSnapshot(self::ID, '2026-11-08 23:00:00', 130);

        $points = $this->readModel()->pointsOf(
            ProductId::fromString(self::ID),
            new \DateTimeImmutable('2026-11-07 23:00:00', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('2026-11-08 23:00:00', new \DateTimeZone('UTC')),
        );

        self::assertSame([110, 120], $this->prices($points));
    }

    #[Test]
    public function it_returns_an_empty_list_without_snapshots(): void
    {
        self::assertSame([], $this->readModel()->pointsOf(ProductId::fromString(self::ID), null, null));
    }

    private function insertProduct(string $id, string $externalId): void
    {
        $this->connection->insert(
            'product',
            [
                'id' => BinaryUuid::toDatabase($id), 'supermarket_id' => 'mercadona', 'external_id' => $externalId,
                'name' => 'Leche', 'price_cents' => 100, 'available' => true, 'last_seen_at' => '2026-11-08 06:00:00',
            ],
            ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN],
        );
    }

    private function insertSnapshot(string $productId, string $capturedAt, int $priceCents, ?int $unitPriceCents = null, ?string $unit = null): void
    {
        $this->connection->insert(
            'price_snapshot',
            [
                'product_id' => BinaryUuid::toDatabase($productId), 'price_cents' => $priceCents,
                'unit_price_cents' => $unitPriceCents, 'unit_price_unit' => $unit, 'captured_at' => $capturedAt,
            ],
            ['product_id' => ParameterType::BINARY],
        );
    }

    /**
     * @param list<PriceHistoryPoint> $points
     *
     * @return list<int>
     */
    private function prices(array $points): array
    {
        return array_map(static fn (PriceHistoryPoint $point): int => $point->priceCents, $points);
    }

    private function readModel(): DbalPriceHistoryReadModel
    {
        return new DbalPriceHistoryReadModel($this->connection);
    }
}
