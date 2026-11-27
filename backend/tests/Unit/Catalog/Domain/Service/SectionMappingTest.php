<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Service;

use App\Catalog\Domain\Model\Section;
use App\Catalog\Domain\Service\SectionMapping;
use App\Catalog\Domain\ValueObject\CategoryPath;
use App\Catalog\Domain\ValueObject\SupermarketId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SectionMappingTest extends TestCase
{
    #[Test]
    public function it_maps_a_category_by_its_top_category(): void
    {
        self::assertSame(Section::DAIRY_AND_EGGS, $this->sectionOf('mercadona', ['Lácteos y huevos', 'Leche']));
    }

    #[Test]
    public function it_maps_a_category_by_its_longest_matching_prefix(): void
    {
        self::assertSame(Section::DELI_AND_CHEESE, $this->sectionOf('aldi', ['Lácteos y huevos', 'Quesos']));
        self::assertSame(Section::DELI_AND_CHEESE, $this->sectionOf('bonpreu', ['Carne y pescado', 'Embutidos']));
        self::assertSame(Section::MEAT_AND_FISH, $this->sectionOf('bonpreu', ['Carne y pescado', 'Pollo']));
    }

    #[Test]
    public function it_matches_whole_segments_only(): void
    {
        self::assertSame(Section::DAIRY_AND_EGGS, $this->sectionOf('mercadona', ['Lácteos y huevos', 'Quesos frescos']));
    }

    #[Test]
    public function a_product_without_category_is_in_other(): void
    {
        self::assertSame(Section::OTHER, new SectionMapping()->sectionOf(SupermarketId::fromString('mercadona'), null));
    }

    #[Test]
    public function an_unknown_category_is_in_other(): void
    {
        self::assertSame(Section::OTHER, $this->sectionOf('mercadona', ['Juguetes', 'Puzles']));
    }

    #[Test]
    public function a_category_of_another_supermarket_is_unknown(): void
    {
        self::assertSame(Section::OTHER, $this->sectionOf('lidl', ['Lácteos y huevos', 'Leche']));
    }

    /**
     * Every dummy product gets a real section (PD-115). The dummy chains share these paths.
     *
     * @param list<string> $path
     */
    #[Test]
    #[DataProvider('dummyCategories')]
    public function it_maps_every_dummy_catalog_category(array $path, Section $expected): void
    {
        foreach (['mercadona', 'bonpreu', 'aldi'] as $supermarket) {
            self::assertSame($expected, $this->sectionOf($supermarket, $path), $supermarket);
        }
    }

    /**
     * @return iterable<string, array{list<string>, Section}>
     */
    public static function dummyCategories(): iterable
    {
        yield 'milk' => [['Lácteos y huevos', 'Leche'], Section::DAIRY_AND_EGGS];
        yield 'cheese' => [['Lácteos y huevos', 'Quesos'], Section::DELI_AND_CHEESE];
        yield 'oils' => [['Despensa', 'Aceites'], Section::PANTRY];
        yield 'water' => [['Bebidas', 'Agua'], Section::DRINKS];
        yield 'fruit' => [['Fruta y verdura', 'Fruta'], Section::FRUIT_AND_VEGETABLES];
        yield 'bread' => [['Panadería', 'Pan'], Section::BAKERY];
        yield 'chicken' => [['Carne y pescado', 'Pollo'], Section::MEAT_AND_FISH];
        yield 'cured sausage' => [['Carne y pescado', 'Embutidos'], Section::DELI_AND_CHEESE];
        yield 'detergent' => [['Limpieza y hogar', 'Detergente'], Section::HOUSEHOLD];
        yield 'shower gel' => [['Cuidado personal', 'Gel'], Section::PERSONAL_CARE];
    }

    /**
     * @param list<string> $path
     */
    private function sectionOf(string $supermarket, array $path): Section
    {
        return new SectionMapping()->sectionOf(SupermarketId::fromString($supermarket), CategoryPath::fromSegments($path));
    }
}
