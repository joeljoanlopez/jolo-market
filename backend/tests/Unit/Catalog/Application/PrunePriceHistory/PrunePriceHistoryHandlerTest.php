<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\PrunePriceHistory;

use App\Catalog\Application\PrunePriceHistory\InvalidRetentionPeriod;
use App\Catalog\Application\PrunePriceHistory\PrunePriceHistoryCommand;
use App\Catalog\Application\PrunePriceHistory\PrunePriceHistoryHandler;
use App\Catalog\Domain\Model\PriceSnapshot;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Support\Catalog\InMemoryPriceSnapshotRepository;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PrunePriceHistoryHandlerTest extends TestCase
{
    private const string MILK = '00000000-0000-4000-8000-000000000001';
    private const string BREAD = '00000000-0000-4000-8000-000000000002';

    private InMemoryPriceSnapshotRepository $snapshots;

    protected function setUp(): void
    {
        $this->snapshots = new InMemoryPriceSnapshotRepository();
    }

    #[Test]
    public function it_deletes_the_snapshots_older_than_the_retention_period(): void
    {
        $this->capture(self::MILK, '2024-11-12 04:59:59');
        $this->capture(self::MILK, '2024-11-12 05:00:00');
        $this->capture(self::MILK, '2026-11-12 05:00:00');

        $result = $this->prune(at: '2026-11-12 05:00:00', months: 24);

        self::assertSame(1, $result->deleted);
        self::assertSame(['2024-11-12 05:00:00', '2026-11-12 05:00:00'], $this->remaining(self::MILK));
    }

    #[Test]
    public function it_keeps_the_latest_snapshot_of_each_product_however_old(): void
    {
        $this->capture(self::MILK, '2023-01-01 05:00:00');
        $this->capture(self::MILK, '2023-06-01 05:00:00');
        $this->capture(self::BREAD, '2023-02-01 05:00:00');
        $this->capture(self::BREAD, '2026-11-01 05:00:00');

        $result = $this->prune(at: '2026-11-12 05:00:00', months: 24);

        self::assertSame(2, $result->deleted);
        self::assertSame(['2023-06-01 05:00:00'], $this->remaining(self::MILK));
        self::assertSame(['2026-11-01 05:00:00'], $this->remaining(self::BREAD));
    }

    #[Test]
    public function it_returns_the_cutoff_it_used(): void
    {
        $result = $this->prune(at: '2026-11-12 05:00:00', months: 24);

        self::assertSame('2024-11-12 05:00:00', $result->cutoff->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_moves_a_cutoff_past_the_end_of_a_shorter_month_to_its_last_day(): void
    {
        // 31 May minus 3 months would be 31 February; PHP would roll it over to 3 March.
        $result = $this->prune(at: '2026-05-31 05:00:00', months: 3);

        self::assertSame('2026-02-28 05:00:00', $result->cutoff->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_deletes_nothing_when_every_snapshot_is_recent(): void
    {
        $this->capture(self::MILK, '2026-11-01 05:00:00');

        self::assertSame(0, $this->prune(at: '2026-11-12 05:00:00', months: 24)->deleted);
    }

    #[Test]
    #[DataProvider('invalidPeriods')]
    public function it_rejects_a_retention_period_below_one_month(int $months): void
    {
        $this->expectException(InvalidRetentionPeriod::class);

        new PrunePriceHistoryCommand($months);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPeriods(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    private function prune(string $at, int $months): \App\Catalog\Application\PrunePriceHistory\PrunePriceHistoryResult
    {
        return new PrunePriceHistoryHandler($this->snapshots, FixedClock::at($at))(new PrunePriceHistoryCommand($months));
    }

    private function capture(string $productId, string $capturedAt): void
    {
        $this->snapshots->append(PriceSnapshot::capture(
            ProductId::fromString($productId),
            Money::eur(100),
            null,
            new \DateTimeImmutable($capturedAt, new \DateTimeZone('UTC')),
        ));
    }

    /**
     * @return list<string>
     */
    private function remaining(string $productId): array
    {
        return array_values(array_map(
            static fn (PriceSnapshot $snapshot): string => $snapshot->capturedAt()->format('Y-m-d H:i:s'),
            array_filter($this->snapshots->all(), static fn (PriceSnapshot $snapshot): bool => $snapshot->productId()->value() === $productId),
        ));
    }
}
