<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\FindUnmappedCategories;

use App\Catalog\Application\FindUnmappedCategories\CategoryCount;
use App\Catalog\Application\FindUnmappedCategories\FindUnmappedCategoriesHandler;
use App\Catalog\Application\FindUnmappedCategories\FindUnmappedCategoriesQuery;
use App\Catalog\Domain\Service\SectionMapping;
use App\Tests\Support\Catalog\InMemoryStoredCategories;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FindUnmappedCategoriesHandlerTest extends TestCase
{
    #[Test]
    public function it_lists_only_the_categories_that_map_to_other(): void
    {
        $categories = new InMemoryStoredCategories([
            new CategoryCount('mercadona', 'Lácteos y huevos > Leche', 12),
            new CategoryCount('mercadona', 'Juguetes > Puzles', 3),
        ]);

        self::assertEquals(
            [new CategoryCount('mercadona', 'Juguetes > Puzles', 3)],
            $this->handler($categories)(new FindUnmappedCategoriesQuery()),
        );
    }

    #[Test]
    public function it_groups_them_by_supermarket_with_the_most_products_first(): void
    {
        $categories = new InMemoryStoredCategories([
            new CategoryCount('mercadona', 'Juguetes', 3),
            new CategoryCount('aldi', 'Ofertas', 2),
            new CategoryCount('mercadona', 'Bazar', 9),
            new CategoryCount('aldi', 'Marcas', 2),
            new CategoryCount('aldi', 'Hogar textil', 5),
        ]);

        self::assertEquals([
            new CategoryCount('aldi', 'Hogar textil', 5),
            new CategoryCount('aldi', 'Marcas', 2),
            new CategoryCount('aldi', 'Ofertas', 2),
            new CategoryCount('mercadona', 'Bazar', 9),
            new CategoryCount('mercadona', 'Juguetes', 3),
        ], $this->handler($categories)(new FindUnmappedCategoriesQuery()));
    }

    #[Test]
    public function it_returns_nothing_when_every_category_has_a_section(): void
    {
        $categories = new InMemoryStoredCategories([new CategoryCount('aldi', 'Despensa > Aceites', 4)]);

        self::assertSame([], $this->handler($categories)(new FindUnmappedCategoriesQuery()));
    }

    private function handler(InMemoryStoredCategories $categories): FindUnmappedCategoriesHandler
    {
        return new FindUnmappedCategoriesHandler($categories, new SectionMapping());
    }
}
