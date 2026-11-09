<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class SearchProductsControllerTest extends CatalogApiTestCase
{
    #[Test]
    public function it_returns_the_matching_products_grouped_by_unit_and_ranked_by_unit_price(): void
    {
        $this->seed(1, 'Leche entera', brand: 'Hacendado', priceCents: 125, unitPriceCents: 83, unit: 'l', packageQuantity: 1500, packageUnit: 'l', imageUrl: 'https://example.com/leche.jpg');
        $this->seed(2, 'Leche semi', supermarket: 'aldi', priceCents: 70, unitPriceCents: 70, unit: 'l');
        $this->seed(3, 'Leche en polvo', priceCents: 450, unitPriceCents: 900, unit: 'kg', packageQuantity: 500, packageUnit: 'kg');
        $this->seed(4, 'Leche pack sorpresa', priceCents: 300, unitPriceCents: null, unit: null, packageQuantity: null, packageUnit: null);
        $this->seed(5, 'Pan de molde');

        $this->client->request('GET', '/api/products?q=leche');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $body = $this->json();
        // A unit → cents map: the order of its keys means nothing.
        self::assertEquals(['kg' => 900, 'l' => 70], $body['cheapestUnitPriceCents']);
        unset($body['cheapestUnitPriceCents']);
        self::assertSame([
            'items' => [
                [
                    'id' => self::id(2),
                    'name' => 'Leche semi',
                    'brand' => null,
                    'supermarket' => ['id' => 'aldi', 'name' => 'Aldi'],
                    'packaging' => ['quantity' => 1, 'unit' => 'l'],
                    'priceCents' => 70,
                    'unitPrice' => ['cents' => 70, 'unit' => 'l'],
                    'imageUrl' => null,
                ],
                [
                    'id' => self::id(1),
                    'name' => 'Leche entera',
                    'brand' => 'Hacendado',
                    'supermarket' => ['id' => 'mercadona', 'name' => 'Mercadona'],
                    'packaging' => ['quantity' => 1.5, 'unit' => 'l'],
                    'priceCents' => 125,
                    'unitPrice' => ['cents' => 83, 'unit' => 'l'],
                    'imageUrl' => 'https://example.com/leche.jpg',
                ],
                [
                    'id' => self::id(3),
                    'name' => 'Leche en polvo',
                    'brand' => null,
                    'supermarket' => ['id' => 'mercadona', 'name' => 'Mercadona'],
                    'packaging' => ['quantity' => 0.5, 'unit' => 'kg'],
                    'priceCents' => 450,
                    'unitPrice' => ['cents' => 900, 'unit' => 'kg'],
                    'imageUrl' => null,
                ],
                [
                    'id' => self::id(4),
                    'name' => 'Leche pack sorpresa',
                    'brand' => null,
                    'supermarket' => ['id' => 'mercadona', 'name' => 'Mercadona'],
                    'packaging' => null,
                    'priceCents' => 300,
                    'unitPrice' => null,
                    'imageUrl' => null,
                ],
            ],
            'page' => 1,
            'perPage' => 20,
            'total' => 4,
        ], $body);
    }

    #[Test]
    public function it_returns_the_requested_page_of_twenty_products(): void
    {
        for ($number = 1; $number <= 21; ++$number) {
            $this->seed($number, 'Leche '.$number, unitPriceCents: $number);
        }

        $this->client->request('GET', '/api/products?q=leche&page=2');

        self::assertResponseStatusCodeSame(200);
        $body = $this->json();
        self::assertIsArray($body['items']);
        self::assertSame([self::id(21)], array_column($body['items'], 'id'));
        self::assertSame(2, $body['page']);
        self::assertSame(20, $body['perPage']);
        self::assertSame(21, $body['total']);
    }

    #[Test]
    public function it_returns_an_empty_page_when_nothing_matches(): void
    {
        $this->client->request('GET', '/api/products?q=leche');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('{"items":[],"page":1,"perPage":20,"total":0,"cheapestUnitPriceCents":{}}', $this->client->getResponse()->getContent());
    }

    #[Test]
    public function it_searches_only_the_supermarket_of_the_filter(): void
    {
        $this->seed(1, 'Leche entera');
        $this->seed(2, 'Leche entera', supermarket: 'aldi');

        $this->client->request('GET', '/api/products?q=leche&supermarket=aldi');

        self::assertResponseStatusCodeSame(200);
        $body = $this->json();
        self::assertIsArray($body['items']);
        self::assertSame([self::id(2)], array_column($body['items'], 'id'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'missing q' => ['/api/products'];
        yield 'empty q' => ['/api/products?q='];
        yield 'q of one character after trimming' => ['/api/products?q=%20l%20'];
        yield 'page zero' => ['/api/products?q=leche&page=0'];
        yield 'page not an integer' => ['/api/products?q=leche&page=abc'];
        yield 'supermarket not enabled' => ['/api/products?q=leche&supermarket=bonpreu'];
        yield 'unknown supermarket' => ['/api/products?q=leche&supermarket=lidl'];
        yield 'malformed supermarket' => ['/api/products?q=leche&supermarket=Not%20A%20Slug'];
    }

    #[Test]
    #[DataProvider('invalidRequests')]
    public function it_rejects_an_invalid_search_with_a_422_problem_json(string $uri): void
    {
        $this->client->request('GET', $uri);

        $this->assertProblem(422);
    }
}
