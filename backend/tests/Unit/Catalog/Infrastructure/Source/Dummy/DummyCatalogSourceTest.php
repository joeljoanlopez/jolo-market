<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Source\Dummy;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Infrastructure\Source\Dummy\DummyCatalogSource;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DummyCatalogSourceTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function chains(): iterable
    {
        yield 'Mercadona' => ['mercadona'];
        yield 'Bonpreu i Esclat' => ['bonpreu'];
        yield 'Aldi' => ['aldi'];
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_returns_between_30_and_50_products_per_chain(string $chain): void
    {
        $products = self::fetch($chain, '2026-11-14 05:00:00');

        self::assertGreaterThanOrEqual(30, \count($products));
        self::assertLessThanOrEqual(50, \count($products));
    }

    /**
     * @return list<SourceProduct>
     */
    private static function fetch(string $chain, string $utcDateTime): array
    {
        $source = new DummyCatalogSource($chain, FixedClock::at($utcDateTime));

        return array_values([...$source->fetchCatalog(PostalCode::fromString('08001'))]);
    }
}
