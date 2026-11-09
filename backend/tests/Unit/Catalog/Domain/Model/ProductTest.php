<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\ProductDetails;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Domain\ValueObject\UnitOfMeasure;
use App\Catalog\Domain\ValueObject\UnitPrice;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    private const string PRODUCT_ID = '0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f';

    #[Test]
    public function it_is_registered_from_its_source_data(): void
    {
        $seenAt = new \DateTimeImmutable('2026-11-08 06:00:00');

        $product = Product::register(
            ProductId::fromString(self::PRODUCT_ID),
            SupermarketId::fromString('mercadona'),
            ExternalProductId::fromString('3400'),
            self::details('Leche entera'),
            Money::eur(99),
            Packaging::litres('1'),
            $seenAt,
        );

        self::assertSame(self::PRODUCT_ID, $product->id()->value());
        self::assertSame('mercadona', $product->supermarketId()->value());
        self::assertSame('3400', $product->externalId()->value());
        self::assertSame('Leche entera', $product->details()->name()->value());
        self::assertSame(99, $product->currentPrice()->cents());
        self::assertTrue(Packaging::litres('1')->equals($product->packaging() ?? self::fail('No packaging.')));
        self::assertEquals($seenAt, $product->lastSeenAt());
        self::assertTrue($product->isAvailable());
    }

    #[Test]
    public function it_derives_its_unit_price_from_the_price_and_the_packaging(): void
    {
        $product = self::product(price: 125, packaging: Packaging::grams('500'));

        self::assertEquals(UnitPrice::of(Money::eur(250), UnitOfMeasure::KILOGRAM), $product->unitPrice());
    }

    #[Test]
    public function it_has_no_unit_price_when_its_packaging_is_unknown(): void
    {
        $product = self::product(packaging: null);

        self::assertNull($product->packaging());
        self::assertNull($product->unitPrice());
    }

    #[Test]
    public function it_uses_the_reference_unit_price_of_its_source_when_its_packaging_is_unknown(): void
    {
        $reference = UnitPrice::of(Money::eur(320), UnitOfMeasure::KILOGRAM);

        $product = self::product(price: 51, packaging: null, referenceUnitPrice: $reference);

        self::assertEquals($reference, $product->unitPrice());
    }

    #[Test]
    public function it_prefers_its_own_unit_price_when_its_packaging_is_known(): void
    {
        $product = self::product(price: 125, packaging: Packaging::grams('500'), referenceUnitPrice: UnitPrice::of(Money::eur(999), UnitOfMeasure::KILOGRAM));

        self::assertEquals(UnitPrice::of(Money::eur(250), UnitOfMeasure::KILOGRAM), $product->unitPrice());
    }

    #[Test]
    public function it_takes_the_new_reference_unit_price_when_updated_from_its_source(): void
    {
        $product = self::product(packaging: null, referenceUnitPrice: UnitPrice::of(Money::eur(320), UnitOfMeasure::KILOGRAM));

        $product->updateFromSource(self::details('Plátano'), Money::eur(53), null, new \DateTimeImmutable('2026-11-09'), UnitPrice::of(Money::eur(330), UnitOfMeasure::KILOGRAM));

        self::assertEquals(UnitPrice::of(Money::eur(330), UnitOfMeasure::KILOGRAM), $product->unitPrice());
    }

    #[Test]
    public function it_is_reconstituted_with_its_reference_unit_price(): void
    {
        $reference = UnitPrice::of(Money::eur(320), UnitOfMeasure::KILOGRAM);

        $product = Product::reconstitute(
            ProductId::fromString(self::PRODUCT_ID),
            SupermarketId::fromString('mercadona'),
            ExternalProductId::fromString('3819'),
            self::details('Plátano'),
            Money::eur(51),
            null,
            new \DateTimeImmutable('2026-11-01 06:00:00'),
            true,
            $reference,
        );

        self::assertEquals($reference, $product->unitPrice());
    }

    #[Test]
    public function it_takes_the_new_price_name_and_packaging_when_updated_from_its_source(): void
    {
        $product = self::product(price: 99, packaging: Packaging::litres('1'));
        $seenAt = new \DateTimeImmutable('2026-11-09 06:00:00');

        $product->updateFromSource(self::details('Leche entera 1,5 L'), Money::eur(140), Packaging::litres('1.5'), $seenAt);

        self::assertSame('Leche entera 1,5 L', $product->details()->name()->value());
        self::assertSame(140, $product->currentPrice()->cents());
        self::assertEquals(UnitPrice::of(Money::eur(93), UnitOfMeasure::LITRE), $product->unitPrice());
        self::assertEquals($seenAt, $product->lastSeenAt());
    }

    #[Test]
    public function it_loses_its_unit_price_when_the_updated_packaging_is_unknown(): void
    {
        $product = self::product(packaging: Packaging::litres('1'));

        $product->updateFromSource(self::details('Leche'), Money::eur(99), null, new \DateTimeImmutable('2026-11-09'));

        self::assertNull($product->unitPrice());
    }

    #[Test]
    public function it_can_be_marked_as_unavailable(): void
    {
        $product = self::product();

        $product->markUnavailable();

        self::assertFalse($product->isAvailable());
    }

    #[Test]
    public function it_becomes_available_again_when_its_source_returns_it(): void
    {
        $product = self::product();
        $product->markUnavailable();

        $product->updateFromSource(self::details('Leche'), Money::eur(99), null, new \DateTimeImmutable('2026-11-09'));

        self::assertTrue($product->isAvailable());
    }

    #[Test]
    public function it_captures_a_snapshot_of_its_current_price(): void
    {
        $product = self::product(price: 125, packaging: Packaging::grams('500'));
        $capturedAt = new \DateTimeImmutable('2026-11-08 06:00:00');

        $snapshot = $product->captureSnapshot($capturedAt);

        self::assertTrue($product->id()->equals($snapshot->productId()));
        self::assertSame(125, $snapshot->price()->cents());
        self::assertEquals(UnitPrice::of(Money::eur(250), UnitOfMeasure::KILOGRAM), $snapshot->unitPrice());
        self::assertEquals($capturedAt, $snapshot->capturedAt());
    }

    #[Test]
    public function it_is_reconstituted_from_its_stored_state(): void
    {
        $lastSeenAt = new \DateTimeImmutable('2026-11-01 06:00:00');

        $product = Product::reconstitute(
            ProductId::fromString(self::PRODUCT_ID),
            SupermarketId::fromString('aldi'),
            ExternalProductId::fromString('A-1'),
            self::details('Pan'),
            Money::eur(125),
            Packaging::grams('500'),
            $lastSeenAt,
            false,
        );

        self::assertSame('aldi', $product->supermarketId()->value());
        self::assertSame('A-1', $product->externalId()->value());
        self::assertEquals(UnitPrice::of(Money::eur(250), UnitOfMeasure::KILOGRAM), $product->unitPrice());
        self::assertEquals($lastSeenAt, $product->lastSeenAt());
        self::assertFalse($product->isAvailable());
    }

    private static function product(int $price = 99, ?Packaging $packaging = null, ?UnitPrice $referenceUnitPrice = null): Product
    {
        return Product::register(
            ProductId::fromString(self::PRODUCT_ID),
            SupermarketId::fromString('mercadona'),
            ExternalProductId::fromString('3400'),
            self::details('Leche'),
            Money::eur($price),
            $packaging,
            new \DateTimeImmutable('2026-11-08 06:00:00'),
            $referenceUnitPrice,
        );
    }

    private static function details(string $name): ProductDetails
    {
        return ProductDetails::of(ProductName::fromString($name), null, null, null);
    }
}
