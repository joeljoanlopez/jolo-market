<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\GetProduct;

use App\Catalog\Application\GetProduct\GetProductHandler;
use App\Catalog\Application\GetProduct\GetProductQuery;
use App\Catalog\Application\GetProduct\ProductNotFound;
use App\Catalog\Application\GetProduct\ProductView;
use App\Tests\Support\Catalog\InMemoryProductViewReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetProductHandlerTest extends TestCase
{
    private const string ID = '00000000-0000-4000-8000-000000000001';

    private InMemoryProductViewReadModel $readModel;

    protected function setUp(): void
    {
        $this->readModel = new InMemoryProductViewReadModel();
    }

    #[Test]
    public function it_returns_the_product(): void
    {
        $product = self::product(available: true);
        $this->readModel->add($product);

        self::assertSame($product, ($this->handler())(new GetProductQuery(self::ID)));
    }

    #[Test]
    public function it_returns_a_product_that_is_no_longer_available(): void
    {
        $product = self::product(available: false);
        $this->readModel->add($product);

        self::assertSame($product, ($this->handler())(new GetProductQuery(self::ID)));
    }

    #[Test]
    public function it_finds_the_product_whatever_the_case_of_the_id(): void
    {
        $product = self::product(available: true);
        $this->readModel->add($product);

        self::assertSame($product, ($this->handler())(new GetProductQuery(strtoupper(self::ID))));
    }

    #[Test]
    public function it_fails_with_not_found_for_an_unknown_id(): void
    {
        $this->expectException(ProductNotFound::class);

        ($this->handler())(new GetProductQuery(self::ID));
    }

    #[Test]
    public function it_fails_with_not_found_for_a_malformed_id(): void
    {
        $this->expectException(ProductNotFound::class);

        ($this->handler())(new GetProductQuery('not-a-uuid'));
    }

    private function handler(): GetProductHandler
    {
        return new GetProductHandler($this->readModel);
    }

    private static function product(bool $available): ProductView
    {
        return new ProductView(self::ID, 'Leche entera', null, 'mercadona', 'Mercadona', 1000, 'l', 99, 99, 'l', null, $available);
    }
}
