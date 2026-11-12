<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class GetPriceHistoryControllerTest extends CatalogApiTestCase
{
    #[Test]
    public function it_returns_the_snapshots_oldest_first(): void
    {
        $this->seed(1, 'Leche entera');
        $this->seedSnapshot(1, '2026-11-09 05:00:00', 130);
        $this->seedSnapshot(1, '2026-11-08 05:00:00', 125, 83, 'l');

        $this->client->request('GET', '/api/products/'.self::id(1).'/price-history');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'productId' => self::id(1),
            'points' => [
                ['capturedAt' => '2026-11-08T05:00:00+00:00', 'priceCents' => 125, 'unitPrice' => ['cents' => 83, 'unit' => 'l']],
                ['capturedAt' => '2026-11-09T05:00:00+00:00', 'priceCents' => 130, 'unitPrice' => null],
            ],
        ], $this->json());
    }

    #[Test]
    public function it_returns_an_empty_list_for_a_product_without_snapshots(): void
    {
        $this->seed(1, 'Leche entera');

        $this->client->request('GET', '/api/products/'.self::id(1).'/price-history');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['productId' => self::id(1), 'points' => []], $this->json());
    }

    #[Test]
    public function it_returns_the_history_of_a_product_that_is_no_longer_available(): void
    {
        $this->seed(1, 'Leche entera', available: false);
        $this->seedSnapshot(1, '2026-11-08 05:00:00', 125);

        $this->client->request('GET', '/api/products/'.self::id(1).'/price-history');

        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $this->points());
    }

    #[Test]
    public function it_filters_by_whole_days_in_madrid_time(): void
    {
        $this->seed(1, 'Leche entera');
        // 2026-11-01 00:00 in Madrid is 2026-10-31 23:00 UTC; the end of 2026-11-02 is 2026-11-02 23:00 UTC.
        $this->seedSnapshot(1, '2026-10-31 22:59:59', 100);
        $this->seedSnapshot(1, '2026-10-31 23:00:00', 110);
        $this->seedSnapshot(1, '2026-11-02 22:59:59', 120);
        $this->seedSnapshot(1, '2026-11-02 23:00:00', 130);

        $this->client->request('GET', '/api/products/'.self::id(1).'/price-history?from=2026-11-01&to=2026-11-02');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([110, 120], array_column($this->points(), 'priceCents'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'malformed from' => ['from=yesterday'];
        yield 'malformed to' => ['to=2026-02-30'];
        yield 'empty from' => ['from='];
        yield 'from after to' => ['from=2026-11-09&to=2026-11-08'];
    }

    #[Test]
    #[DataProvider('invalidRanges')]
    public function it_answers_an_invalid_range_with_a_422_problem_json(string $queryString): void
    {
        $this->seed(1, 'Leche entera');

        $this->client->request('GET', '/api/products/'.self::id(1).'/price-history?'.$queryString);

        $this->assertProblem(422);
    }

    #[Test]
    public function it_answers_an_unknown_product_with_a_404_problem_json(): void
    {
        $this->client->request('GET', '/api/products/'.self::id(1).'/price-history');

        $this->assertProblem(404);
    }

    #[Test]
    public function it_answers_a_malformed_id_with_a_404_problem_json(): void
    {
        $this->client->request('GET', '/api/products/not-a-uuid/price-history');

        $this->assertProblem(404);
    }

    /**
     * @return list<array<mixed>>
     */
    private function points(): array
    {
        $points = $this->json()['points'] ?? null;
        self::assertIsArray($points);

        return array_values(array_filter($points, is_array(...)));
    }
}
