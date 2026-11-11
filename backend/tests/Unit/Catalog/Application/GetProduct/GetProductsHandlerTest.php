<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\GetProduct;

use App\Catalog\Application\GetProduct\GetProductsHandler;
use App\Catalog\Application\GetProduct\GetProductsQuery;
use App\Catalog\Application\GetProduct\ProductView;
use App\Tests\Support\Catalog\InMemoryProductViewReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetProductsHandlerTest extends TestCase
{
    private const string MILK = '00000000-0000-4000-8000-000000000001';
    private const string WATER = '00000000-0000-4000-8000-000000000002';
    private const string UNKNOWN = '00000000-0000-4000-8000-000000000099';

    #[Test]
    public function it_returns_the_known_products_by_id_available_or_not(): void
    {
        $readModel = new InMemoryProductViewReadModel();
        $milk = self::product(self::MILK, available: true);
        $water = self::product(self::WATER, available: false);
        $readModel->add($milk);
        $readModel->add($water);

        $products = (new GetProductsHandler($readModel))(new GetProductsQuery([self::WATER, self::UNKNOWN, 'not-a-uuid', self::MILK, strtoupper(self::MILK)]));

        self::assertSame([self::WATER => $water, self::MILK => $milk], $products);
    }

    #[Test]
    public function no_ids_find_no_products(): void
    {
        self::assertSame([], (new GetProductsHandler(new InMemoryProductViewReadModel()))(new GetProductsQuery([])));
    }

    private static function product(string $id, bool $available): ProductView
    {
        return new ProductView($id, 'Leche', null, 'mercadona', 'Mercadona', 1000, 'l', 125, 125, 'l', null, $available);
    }
}
