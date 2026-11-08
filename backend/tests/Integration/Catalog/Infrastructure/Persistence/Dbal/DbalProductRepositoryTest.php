<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\ValueObject\CategoryPath;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\ProductDetails;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Domain\ValueObject\UnitOfMeasure;
use App\Catalog\Domain\ValueObject\UnitPrice;
use App\Catalog\Domain\ValueObject\Url;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalProductRepository;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;

final class DbalProductRepositoryTest extends DbalTestCase
{
    private DbalProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DbalProductRepository($this->connection);
    }

    #[Test]
    public function it_saves_a_new_product_and_finds_it_by_supermarket_and_external_id(): void
    {
        $product = Product::register(
            ProductId::fromString('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f'),
            SupermarketId::fromString('mercadona'),
            ExternalProductId::fromString('3400'),
            ProductDetails::of(
                ProductName::fromString('Leche entera'),
                'Hacendado',
                CategoryPath::fromSegments(['Lácteos', 'Leche']),
                Url::fromString('https://example.com/leche.jpg'),
            ),
            Money::eur(125),
            Packaging::grams('500'),
            self::utc('2026-11-08 06:00:00.123456'),
        );

        $this->repository->save($product);
        $found = $this->repository->findBySupermarketAndExternalId(SupermarketId::fromString('mercadona'), ExternalProductId::fromString('3400'));

        self::assertEquals($product, $found);
    }

    #[Test]
    public function it_saves_a_product_without_optional_data(): void
    {
        $product = self::product('1', withPackaging: false);

        $this->repository->save($product);

        self::assertEquals($product, $this->find('mercadona', '1'));
    }

    #[Test]
    public function it_saves_a_product_priced_by_its_reference_unit_price(): void
    {
        $product = Product::register(
            ProductId::fromString('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f'),
            SupermarketId::fromString('mercadona'),
            ExternalProductId::fromString('3819'),
            ProductDetails::of(ProductName::fromString('Plátano'), null, null, null),
            Money::eur(51),
            null,
            self::utc('2026-11-08 06:00:00'),
            UnitPrice::of(Money::eur(320), UnitOfMeasure::KILOGRAM),
        );

        $this->repository->save($product);

        self::assertEquals($product, $this->find('mercadona', '3819'));
    }

    #[Test]
    public function it_returns_null_for_an_unknown_product(): void
    {
        self::assertNull($this->find('mercadona', 'unknown'));
    }

    #[Test]
    public function it_does_not_find_a_product_of_another_supermarket(): void
    {
        $this->repository->save(self::product('1', supermarket: 'aldi'));

        self::assertNull($this->find('mercadona', '1'));
    }

    #[Test]
    public function it_updates_a_product_that_is_saved_again(): void
    {
        $product = self::product('1');
        $this->repository->save($product);

        $product->updateFromSource(
            ProductDetails::of(ProductName::fromString('Leche semi'), null, null, null),
            Money::eur(105),
            Packaging::litres('1.5'),
            self::utc('2026-11-09 06:00:00'),
        );
        $product->markUnavailable();
        $this->repository->save($product);

        self::assertEquals($product, $this->find('mercadona', '1'));
        self::assertSame(1, $this->countRows('product'));
    }

    #[Test]
    public function it_finds_the_available_products_of_a_supermarket_last_seen_before_an_instant(): void
    {
        $this->repository->save(self::product('seen-before', lastSeenAt: '2026-11-07 06:00:00'));
        $this->repository->save(self::product('seen-at-instant', lastSeenAt: '2026-11-08 06:00:00'));
        $unavailable = self::product('unavailable', lastSeenAt: '2026-11-07 06:00:00');
        $unavailable->markUnavailable();
        $this->repository->save($unavailable);
        $this->repository->save(self::product('other-supermarket', supermarket: 'aldi', lastSeenAt: '2026-11-07 06:00:00'));

        $found = $this->repository->findAvailableNotSeenSince(SupermarketId::fromString('mercadona'), self::utc('2026-11-08 06:00:00'));

        $externalIds = array_map(static fn (Product $product): string => $product->externalId()->value(), [...$found]);
        self::assertSame(['seen-before'], $externalIds);
    }

    private function find(string $supermarket, string $externalId): ?Product
    {
        return $this->repository->findBySupermarketAndExternalId(SupermarketId::fromString($supermarket), ExternalProductId::fromString($externalId));
    }

    private static function product(
        string $externalId,
        string $supermarket = 'mercadona',
        bool $withPackaging = true,
        string $lastSeenAt = '2026-11-08 06:00:00',
    ): Product {
        return Product::register(
            ProductId::fromString(\sprintf('00000000-0000-4000-8000-%012d', crc32($supermarket.$externalId) % 1_000_000_000_000)),
            SupermarketId::fromString($supermarket),
            ExternalProductId::fromString($externalId),
            ProductDetails::of(ProductName::fromString('Leche'), null, null, null),
            Money::eur(99),
            $withPackaging ? Packaging::litres('1') : null,
            self::utc($lastSeenAt),
        );
    }

    private static function utc(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
    }
}
