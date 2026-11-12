<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\GetPriceHistory;

use App\Catalog\Application\GetPriceHistory\GetPriceHistoryHandler;
use App\Catalog\Application\GetPriceHistory\GetPriceHistoryQuery;
use App\Catalog\Application\GetPriceHistory\InvalidPriceHistoryRange;
use App\Catalog\Application\GetPriceHistory\PriceHistoryPoint;
use App\Catalog\Application\GetProduct\ProductNotFound;
use App\Catalog\Application\GetProduct\ProductView;
use App\Tests\Support\Catalog\InMemoryPriceHistoryReadModel;
use App\Tests\Support\Catalog\InMemoryProductViewReadModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetPriceHistoryHandlerTest extends TestCase
{
    private const string ID = '00000000-0000-4000-8000-000000000001';
    private const string OTHER_ID = '00000000-0000-4000-8000-000000000002';

    private InMemoryProductViewReadModel $products;
    private InMemoryPriceHistoryReadModel $history;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductViewReadModel();
        $this->history = new InMemoryPriceHistoryReadModel();
        $this->products->add(self::product(self::ID));
    }

    #[Test]
    public function it_returns_the_snapshots_of_the_product_ordered_by_date(): void
    {
        $this->history->add(self::ID, self::point('2026-11-10T05:00:00Z', 130));
        $this->history->add(self::ID, self::point('2026-11-08T05:00:00Z', 125));
        $this->history->add(self::ID, self::point('2026-11-09T05:00:00Z', 125));

        $history = ($this->handler())(new GetPriceHistoryQuery(self::ID));

        self::assertSame(self::ID, $history->productId);
        self::assertSame(
            ['2026-11-08T05:00:00+00:00', '2026-11-09T05:00:00+00:00', '2026-11-10T05:00:00+00:00'],
            self::dates($history->points),
        );
    }

    #[Test]
    public function it_returns_only_the_snapshots_of_the_requested_product(): void
    {
        $this->products->add(self::product(self::OTHER_ID));
        $this->history->add(self::ID, self::point('2026-11-08T05:00:00Z', 125));
        $this->history->add(self::OTHER_ID, self::point('2026-11-09T05:00:00Z', 300));

        $history = ($this->handler())(new GetPriceHistoryQuery(self::ID));

        self::assertSame(['2026-11-08T05:00:00+00:00'], self::dates($history->points));
    }

    #[Test]
    public function it_returns_an_empty_history_for_a_product_without_snapshots(): void
    {
        self::assertSame([], ($this->handler())(new GetPriceHistoryQuery(self::ID))->points);
    }

    #[Test]
    public function it_keeps_the_days_of_the_range_inclusive_in_madrid_time(): void
    {
        // 2026-11-01 00:00 in Madrid (CET, UTC+1) is 2026-10-31 23:00 UTC.
        $this->history->add(self::ID, self::point('2026-10-31T22:59:59Z', 100));
        $this->history->add(self::ID, self::point('2026-10-31T23:00:00Z', 110));
        $this->history->add(self::ID, self::point('2026-11-02T22:59:59Z', 120));
        $this->history->add(self::ID, self::point('2026-11-02T23:00:00Z', 130));

        $history = ($this->handler())(new GetPriceHistoryQuery(self::ID, from: '2026-11-01', to: '2026-11-02'));

        self::assertSame(['2026-10-31T23:00:00+00:00', '2026-11-02T22:59:59+00:00'], self::dates($history->points));
    }

    #[Test]
    public function it_follows_summer_time_at_the_edges_of_the_range(): void
    {
        // 2026-07-01 00:00 in Madrid (CEST, UTC+2) is 2026-06-30 22:00 UTC.
        $this->history->add(self::ID, self::point('2026-06-30T21:59:59Z', 100));
        $this->history->add(self::ID, self::point('2026-06-30T22:00:00Z', 110));

        $history = ($this->handler())(new GetPriceHistoryQuery(self::ID, from: '2026-07-01'));

        self::assertSame(['2026-06-30T22:00:00+00:00'], self::dates($history->points));
    }

    #[Test]
    public function it_accepts_a_range_of_one_day(): void
    {
        $this->history->add(self::ID, self::point('2026-11-08T05:00:00Z', 125));

        $history = ($this->handler())(new GetPriceHistoryQuery(self::ID, from: '2026-11-08', to: '2026-11-08'));

        self::assertSame(['2026-11-08T05:00:00+00:00'], self::dates($history->points));
    }

    #[Test]
    public function it_accepts_only_an_end_date(): void
    {
        $this->history->add(self::ID, self::point('2026-11-08T05:00:00Z', 125));
        $this->history->add(self::ID, self::point('2026-11-09T05:00:00Z', 130));

        $history = ($this->handler())(new GetPriceHistoryQuery(self::ID, to: '2026-11-08'));

        self::assertSame(['2026-11-08T05:00:00+00:00'], self::dates($history->points));
    }

    #[Test]
    public function it_returns_the_prices_of_each_snapshot(): void
    {
        $point = new PriceHistoryPoint(new \DateTimeImmutable('2026-11-08T05:00:00Z'), 125, 83, 'l');
        $this->history->add(self::ID, $point);

        self::assertEquals([$point], ($this->handler())(new GetPriceHistoryQuery(self::ID))->points);
    }

    #[Test]
    public function it_fails_with_not_found_for_an_unknown_product(): void
    {
        $this->expectException(ProductNotFound::class);

        ($this->handler())(new GetPriceHistoryQuery(self::OTHER_ID));
    }

    #[Test]
    public function it_fails_with_not_found_for_a_malformed_id(): void
    {
        $this->expectException(ProductNotFound::class);

        ($this->handler())(new GetPriceHistoryQuery('not-a-uuid'));
    }

    #[Test]
    public function it_rejects_a_start_date_after_the_end_date(): void
    {
        $this->expectException(InvalidPriceHistoryRange::class);

        new GetPriceHistoryQuery(self::ID, from: '2026-11-09', to: '2026-11-08');
    }

    #[Test]
    #[DataProvider('malformedDates')]
    public function it_rejects_a_malformed_date(string $date): void
    {
        $this->expectException(InvalidPriceHistoryRange::class);

        new GetPriceHistoryQuery(self::ID, from: $date);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDates(): iterable
    {
        yield 'empty' => [''];
        yield 'not a date' => ['yesterday'];
        yield 'impossible day' => ['2026-02-30'];
        yield 'impossible month' => ['2026-13-01'];
        yield 'without leading zeros' => ['2026-1-5'];
        yield 'with a time' => ['2026-11-08T00:00:00'];
    }

    #[Test]
    public function it_rejects_a_malformed_end_date(): void
    {
        $this->expectException(InvalidPriceHistoryRange::class);

        new GetPriceHistoryQuery(self::ID, to: '08/11/2026');
    }

    private function handler(): GetPriceHistoryHandler
    {
        return new GetPriceHistoryHandler($this->products, $this->history);
    }

    private static function product(string $id): ProductView
    {
        return new ProductView($id, 'Leche entera', null, 'mercadona', 'Mercadona', 1000, 'l', 125, 125, 'l', null, true);
    }

    private static function point(string $capturedAt, int $priceCents): PriceHistoryPoint
    {
        return new PriceHistoryPoint(new \DateTimeImmutable($capturedAt), $priceCents, null, null);
    }

    /**
     * @param list<PriceHistoryPoint> $points
     *
     * @return list<string>
     */
    private static function dates(array $points): array
    {
        return array_map(
            static fn (PriceHistoryPoint $point): string => $point->capturedAt->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM),
            $points,
        );
    }
}
