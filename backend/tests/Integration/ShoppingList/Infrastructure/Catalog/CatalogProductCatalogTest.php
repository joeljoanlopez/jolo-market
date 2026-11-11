<?php

declare(strict_types=1);

namespace App\Tests\Integration\ShoppingList\Infrastructure\Catalog;

use App\Catalog\Application\GetProduct\GetProductsHandler;
use App\Catalog\Application\GetProduct\ProductView;
use App\ShoppingList\Application\CatalogProduct;
use App\ShoppingList\Application\ProductCatalog;
use App\ShoppingList\Domain\ValueObject\ProductId;
use App\ShoppingList\Infrastructure\Catalog\CatalogProductCatalog;
use App\Tests\Support\Catalog\InMemoryProductViewReadModel;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogProductCatalogTest extends KernelTestCase
{
    private const string MILK = '0191bbbb-0000-7000-8000-000000000001';
    private const string WATER = '0191bbbb-0000-7000-8000-000000000002';
    private const string UNKNOWN = '0191bbbb-0000-7000-8000-000000000099';

    #[Test]
    public function it_reads_current_product_data_through_the_catalog_query_api(): void
    {
        $readModel = new InMemoryProductViewReadModel();
        $readModel->add(new ProductView(self::MILK, 'Leche entera', 'Hacendado', 'mercadona', 'Mercadona', 1000, 'l', 125, 125, 'l', 'https://example.com/milk.jpg', true));
        $readModel->add(new ProductView(self::WATER, 'Agua', null, 'aldi', 'Aldi', null, null, 30, null, null, null, false));
        $catalog = new CatalogProductCatalog(new GetProductsHandler($readModel));

        $products = $catalog->findByIds([ProductId::fromString(self::MILK), ProductId::fromString(self::UNKNOWN), ProductId::fromString(self::WATER)]);

        self::assertEquals([
            self::MILK => new CatalogProduct(self::MILK, 'Leche entera', 'Hacendado', 'mercadona', 'Mercadona', 125, 'https://example.com/milk.jpg', true),
            self::WATER => new CatalogProduct(self::WATER, 'Agua', null, 'aldi', 'Aldi', 30, null, false),
        ], $products);
    }

    #[Test]
    public function it_is_the_catalog_the_application_gets(): void
    {
        self::assertInstanceOf(CatalogProductCatalog::class, self::getContainer()->get(ProductCatalog::class));
    }
}
