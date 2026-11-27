<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Source\Dummy;

use App\Catalog\Domain\Model\Section;
use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\Service\SectionMapping;
use App\Catalog\Domain\ValueObject\CategoryPath;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;
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
     * PD-116: development shows every product under a real section.
     */
    #[Test]
    #[DataProvider('chains')]
    public function every_well_formed_product_has_a_section_other_than_other(string $chain): void
    {
        $mapping = new SectionMapping();
        foreach (self::fetch($chain, '2026-11-14 05:00:00') as $product) {
            if ('dummy-malformed' === $product->externalId) {
                continue;
            }
            $section = $mapping->sectionOf(SupermarketId::fromString($chain), CategoryPath::fromSegments($product->categoryPath));

            self::assertNotSame(Section::OTHER, $section, $product->name);
        }
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
