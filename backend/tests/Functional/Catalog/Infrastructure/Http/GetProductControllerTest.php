<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Test;

final class GetProductControllerTest extends CatalogApiTestCase
{
    #[Test]
    public function it_returns_the_product(): void
    {
        $this->seed(1, 'Leche entera', brand: 'Hacendado', priceCents: 125, unitPriceCents: 83, unit: 'l', packageQuantity: 1500, packageUnit: 'l', imageUrl: 'https://example.com/leche.jpg');

        $this->client->request('GET', '/api/products/'.self::id(1));

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'id' => self::id(1),
            'name' => 'Leche entera',
            'brand' => 'Hacendado',
            'supermarket' => ['id' => 'mercadona', 'name' => 'Mercadona'],
            'packaging' => ['quantity' => 1.5, 'unit' => 'l'],
            'priceCents' => 125,
            'unitPrice' => ['cents' => 83, 'unit' => 'l'],
            'imageUrl' => 'https://example.com/leche.jpg',
            'available' => true,
        ], $this->json());
    }

    #[Test]
    public function it_returns_a_product_that_is_no_longer_available(): void
    {
        $this->seed(1, 'Leche entera', unitPriceCents: null, unit: null, packageQuantity: null, packageUnit: null, available: false);

        $this->client->request('GET', '/api/products/'.self::id(1));

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'id' => self::id(1),
            'name' => 'Leche entera',
            'brand' => null,
            'supermarket' => ['id' => 'mercadona', 'name' => 'Mercadona'],
            'packaging' => null,
            'priceCents' => 100,
            'unitPrice' => null,
            'imageUrl' => null,
            'available' => false,
        ], $this->json());
    }

    #[Test]
    public function it_answers_an_unknown_id_with_a_404_problem_json(): void
    {
        $this->client->request('GET', '/api/products/'.self::id(1));

        $this->assertProblem(404);
    }

    #[Test]
    public function it_answers_a_malformed_id_with_a_404_problem_json(): void
    {
        $this->client->request('GET', '/api/products/not-a-uuid');

        $this->assertProblem(404);
    }
}
