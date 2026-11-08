<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\CategoryPath;
use App\Catalog\Domain\ValueObject\ExternalProductId;
use App\Catalog\Domain\ValueObject\ProductDetails;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Domain\ValueObject\Url;
use App\Shared\Domain\Exception\InvalidArgument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProductValueObjectsTest extends TestCase
{
    #[Test]
    public function a_product_id_is_a_uuid(): void
    {
        self::assertSame(
            '0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f',
            ProductId::fromString('0192F3A4-7B1C-7D2E-8F30-4A5B6C7D8E9F')->value(),
        );
    }

    #[Test]
    public function an_external_product_id_is_trimmed(): void
    {
        self::assertSame('3400', ExternalProductId::fromString(' 3400 ')->value());
    }

    #[Test]
    public function a_product_name_is_trimmed_and_its_inner_whitespace_collapsed(): void
    {
        self::assertSame('Leche entera Hacendado', ProductName::fromString("  Leche  entera\tHacendado ")->value());
    }

    #[Test]
    public function a_category_path_keeps_its_non_blank_segments_in_order(): void
    {
        $path = CategoryPath::fromSegments(['Lácteos', ' ', ' Leche ']);

        self::assertSame(['Lácteos', 'Leche'], $path->segments());
        self::assertSame('Lácteos > Leche', $path->toString());
    }

    #[Test]
    public function a_url_accepts_http_and_https(): void
    {
        self::assertSame('https://example.com/a.jpg', Url::fromString('https://example.com/a.jpg')->value());
        self::assertSame('http://example.com/a.jpg', Url::fromString('http://example.com/a.jpg')->value());
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function it_rejects_an_invalid_value(callable $create): void
    {
        $this->expectException(InvalidArgument::class);

        $create();
    }

    /**
     * @return iterable<string, array{callable(): object}>
     */
    public static function invalidValues(): iterable
    {
        yield 'malformed product id' => [static fn (): object => ProductId::fromString('42')];
        yield 'blank external id' => [static fn (): object => ExternalProductId::fromString(' ')];
        yield 'blank product name' => [static fn (): object => ProductName::fromString(' ')];
        yield 'category path without segments' => [static fn (): object => CategoryPath::fromSegments([' '])];
        yield 'relative url' => [static fn (): object => Url::fromString('/img/a.jpg')];
        yield 'url with another scheme' => [static fn (): object => Url::fromString('ftp://example.com/a.jpg')];
    }

    #[Test]
    public function product_details_turn_a_blank_brand_into_no_brand(): void
    {
        $details = ProductDetails::of(ProductName::fromString('Leche'), '  ', null, null);

        self::assertNull($details->brand());
    }

    #[Test]
    public function product_details_trim_the_brand(): void
    {
        $details = ProductDetails::of(ProductName::fromString('Leche'), ' Hacendado ', null, null);

        self::assertSame('Hacendado', $details->brand());
    }
}
