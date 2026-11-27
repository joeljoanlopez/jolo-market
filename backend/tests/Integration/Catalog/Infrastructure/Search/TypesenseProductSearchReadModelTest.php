<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Search;

use App\Catalog\Application\SearchProducts\ProductSearchPage;
use App\Catalog\Application\SearchProducts\ProductSearchResult;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Search\TypesenseProductSearchReadModel;
use PHPUnit\Framework\Attributes\Test;

/**
 * The test environment enables mercadona and aldi only, so Bonpreu's products are never indexed; the bilingual cases
 * put the Catalan names in Aldi.
 */
final class TypesenseProductSearchReadModelTest extends TypesenseTestCase
{
    private const array ALL_SUPERMARKETS = ['mercadona', 'aldi'];

    /** The products are indexed at the first search of each test, once they are all seeded. */
    private bool $indexed = false;

    #[Test]
    public function it_maps_every_field_of_a_result(): void
    {
        $this->seed(1, 'Leche entera', brand: 'Hacendado', supermarket: 'aldi', priceCents: 125, unitPriceCents: 83, unit: 'l', packageQuantity: 1500, packageUnit: 'l', imageUrl: 'https://example.com/leche.jpg');

        $page = $this->search('leche');

        self::assertEquals([new ProductSearchResult(self::id(1), 'Leche entera', 'Hacendado', 'aldi', 'Aldi', 1500, 'l', 125, 83, 'l', 'https://example.com/leche.jpg')], $page->results);
    }

    #[Test]
    public function it_maps_a_result_without_optional_data(): void
    {
        $this->seed(1, 'Leche', unitPriceCents: null, unit: null, packageQuantity: null, packageUnit: null);

        $page = $this->search('leche');

        self::assertEquals([new ProductSearchResult(self::id(1), 'Leche', null, 'mercadona', 'Mercadona', null, null, 100, null, null, null)], $page->results);
    }

    #[Test]
    public function it_ignores_accents_and_case(): void
    {
        $this->seed(1, 'Plátano de Canarias');
        $this->seed(2, 'Leche entera');

        self::assertSame([self::id(1)], self::ids($this->search('platano')));
        self::assertSame([self::id(1)], self::ids($this->search('PLÁTANO')));
        self::assertSame([self::id(2)], self::ids($this->search('LECHE')));
    }

    #[Test]
    public function it_matches_each_word_as_the_start_of_a_word(): void
    {
        $this->seed(1, 'Plátanos de Canarias');
        $this->seed(2, 'Leche entera');

        self::assertSame([self::id(1)], self::ids($this->search('platan')));
        self::assertSame([self::id(1)], self::ids($this->search('platano')));
        self::assertSame([], self::ids($this->search('tera')));
    }

    #[Test]
    public function it_requires_every_word_to_match_the_name_or_the_brand(): void
    {
        $this->seed(1, 'Leche entera', brand: 'Hacendado');
        $this->seed(2, 'Leche entera', brand: 'Pascual');
        $this->seed(3, 'Agua mineral', brand: 'Hacendado');

        self::assertSame([self::id(1)], self::ids($this->search('leche hacendado')));
    }

    #[Test]
    public function it_matches_short_words_and_stopwords(): void
    {
        $this->seed(1, 'Camiseta (XL)');
        $this->seed(2, 'Camiseta M');
        $this->seed(3, 'Pan de molde');
        $this->seed(4, 'Pan integral molde');

        self::assertSame([self::id(1)], self::ids($this->search('camiseta xl')));
        self::assertSame([self::id(3)], self::ids($this->search('pan de molde')));
        self::assertSame([self::id(1)], self::ids($this->search('xl')));
    }

    #[Test]
    public function it_treats_search_operators_typed_by_the_user_as_separators(): void
    {
        $this->seed(1, 'Leche entera');
        $this->seed(2, 'Leche desnatada');

        self::assertSame([self::id(1)], self::ids($this->search('leche -entera')));
        self::assertSame([self::id(1)], self::ids($this->search('"+leche* (entera)~ <@>')));
    }

    #[Test]
    public function it_finds_nothing_when_the_text_has_no_words(): void
    {
        $this->seed(1, 'Leche entera');

        $page = $this->search(' +-* ');

        self::assertSame([], $page->results);
        self::assertSame(0, $page->total);
        self::assertSame([], $page->cheapestUnitPriceCentsByUnit);
    }

    #[Test]
    public function it_groups_results_by_unit_with_the_most_frequent_unit_first(): void
    {
        $this->seed(1, 'Leche en polvo', unitPriceCents: 50, unit: 'kg');
        $this->seed(2, 'Leche entera', unitPriceCents: 100, unit: 'l');
        $this->seed(3, 'Leche semi', unitPriceCents: 90, unit: 'l');

        self::assertSame([self::id(3), self::id(2), self::id(1)], self::ids($this->search('leche')));
    }

    #[Test]
    public function it_orders_units_with_as_many_results_as_kg_then_l_then_unit(): void
    {
        $this->seed(1, 'Leche pack', unitPriceCents: 10, unit: 'unit');
        $this->seed(2, 'Leche entera', unitPriceCents: 10, unit: 'l');
        $this->seed(3, 'Leche en polvo', unitPriceCents: 10, unit: 'kg');

        self::assertSame([self::id(3), self::id(2), self::id(1)], self::ids($this->search('leche')));
    }

    #[Test]
    public function it_orders_each_unit_group_by_unit_price_ascending(): void
    {
        $this->seed(1, 'Leche A', unitPriceCents: 120, unit: 'l');
        $this->seed(2, 'Leche B', unitPriceCents: 80, unit: 'l');
        $this->seed(3, 'Leche C', unitPriceCents: 100, unit: 'l');

        self::assertSame([self::id(2), self::id(3), self::id(1)], self::ids($this->search('leche')));
    }

    #[Test]
    public function it_puts_products_without_a_unit_price_last(): void
    {
        $this->seed(1, 'Leche sin formato', unitPriceCents: null, unit: null);
        $this->seed(2, 'Leche rara', unitPriceCents: null, unit: null);
        $this->seed(3, 'Leche entera', unitPriceCents: 999, unit: 'l');

        self::assertSame([self::id(3), self::id(2), self::id(1)], self::ids($this->search('leche')));
    }

    #[Test]
    public function it_breaks_unit_price_ties_by_lower_price_then_name_then_id(): void
    {
        $this->seed(4, 'Leche B', priceCents: 100, unitPriceCents: 90, unit: 'l');
        $this->seed(3, 'Leche B', priceCents: 100, unitPriceCents: 90, unit: 'l');
        $this->seed(2, 'Leche A', priceCents: 100, unitPriceCents: 90, unit: 'l');
        $this->seed(1, 'Leche Z', priceCents: 50, unitPriceCents: 90, unit: 'l');

        self::assertSame([self::id(1), self::id(2), self::id(3), self::id(4)], self::ids($this->search('leche')));
    }

    #[Test]
    public function it_searches_only_the_given_supermarkets(): void
    {
        $this->seed(1, 'Leche entera', supermarket: 'mercadona');
        $this->seed(2, 'Leche entera', supermarket: 'aldi', unitPriceCents: 200);

        self::assertSame([self::id(2)], self::ids($this->search('leche', ['aldi'])));
        self::assertSame([self::id(1)], self::ids($this->search('leche', ['mercadona'])));
        self::assertSame([self::id(1), self::id(2)], self::ids($this->search('leche', ['mercadona', 'aldi'])));
        self::assertSame([], self::ids($this->search('leche', [])));
    }

    #[Test]
    public function it_excludes_unavailable_products(): void
    {
        $this->seed(1, 'Leche entera');
        $this->seed(2, 'Leche semi', available: false);

        self::assertSame([self::id(1)], self::ids($this->search('leche')));
    }

    #[Test]
    public function it_returns_the_requested_page_and_the_total_of_the_whole_query(): void
    {
        foreach ([1, 2, 3, 4, 5] as $number) {
            $this->seed($number, 'Leche '.$number, unitPriceCents: 10 * $number);
        }
        $this->seed(6, 'Agua');

        $page = $this->search('leche', page: 2, perPage: 2);

        self::assertSame([self::id(3), self::id(4)], self::ids($page));
        self::assertSame(5, $page->total);
        self::assertSame(2, $page->page);
        self::assertSame(2, $page->perPage);
        self::assertSame([self::id(5)], self::ids($this->search('leche', page: 3, perPage: 2)));
        self::assertSame(5, $this->search('leche', page: 9, perPage: 2)->total);
    }

    #[Test]
    public function it_returns_the_cheapest_unit_price_of_each_unit_over_the_whole_query(): void
    {
        $this->seed(1, 'Leche entera', unitPriceCents: 99, unit: 'l');
        $this->seed(2, 'Leche semi', unitPriceCents: 89, unit: 'l');
        $this->seed(3, 'Leche en polvo', unitPriceCents: 1200, unit: 'kg');
        $this->seed(4, 'Leche sin formato', unitPriceCents: null, unit: null);
        $this->seed(5, 'Leche barata', unitPriceCents: 1, unit: 'l', available: false);

        $cheapest = $this->search('leche', perPage: 1)->cheapestUnitPriceCentsByUnit;
        ksort($cheapest);

        self::assertSame(['kg' => 1200, 'l' => 89], $cheapest);
    }

    #[Test]
    public function a_word_that_is_a_dictionary_entry_also_finds_its_equivalents(): void
    {
        $this->seed(1, 'Leche entera', supermarket: 'mercadona');
        $this->seed(2, 'Llet sencera', supermarket: 'aldi', unitPriceCents: 90);
        $this->seed(3, 'Lletuga', supermarket: 'aldi', unitPriceCents: 80, unit: 'kg');

        self::assertSame([self::id(2), self::id(1), self::id(3)], self::ids($this->search('leche')), 'llet is searched as a prefix: Lletuga is noise PD-101 accepts');
        self::assertSame([self::id(2), self::id(1), self::id(3)], self::ids($this->search('llet')));
        self::assertSame([self::id(2), self::id(1)], self::ids($this->search('leche entera')));
        self::assertSame([self::id(2), self::id(1)], self::ids($this->search('LLET Sencera')));
    }

    #[Test]
    public function pan_and_pa_both_find_the_bread_of_every_supermarket(): void
    {
        $this->seed(1, 'Pan de molde', supermarket: 'mercadona', unitPriceCents: 300, unit: 'kg');
        $this->seed(2, 'Pa de pagès', supermarket: 'aldi', unitPriceCents: 400, unit: 'kg');
        $this->seed(3, 'Pasta espaguetis', supermarket: 'aldi', unitPriceCents: 200, unit: 'kg');
        $this->seed(4, 'Leche entera');

        self::assertSame([self::id(3), self::id(1), self::id(2)], self::ids($this->search('pan')));
        self::assertSame([self::id(3), self::id(1), self::id(2)], self::ids($this->search('pa')));
    }

    #[Test]
    public function a_word_that_is_only_the_start_of_an_entry_gets_no_equivalent(): void
    {
        $this->seed(1, 'Leche entera', supermarket: 'mercadona');
        $this->seed(2, 'Llet sencera', supermarket: 'aldi');

        self::assertSame([self::id(1)], self::ids($this->search('lec')));
        self::assertSame([self::id(2)], self::ids($this->search('lle')));
    }

    #[Test]
    public function it_finds_nothing_without_an_exact_match(): void
    {
        $this->seed(1, 'Leche entera');

        $page = $this->search('lece');

        self::assertSame([], $page->results);
        self::assertSame(0, $page->total);
        self::assertFalse($page->approximate);
    }

    /**
     * @param list<string> $supermarkets
     */
    private function search(string $text, array $supermarkets = self::ALL_SUPERMARKETS, int $page = 1, int $perPage = 20): ProductSearchPage
    {
        if (!$this->indexed) {
            $this->index(__DIR__.'/../../../../Fixtures/Search/search.yaml')->rebuild();
            $this->indexed = true;
        }

        return new TypesenseProductSearchReadModel($this->typesense, self::ALIAS)
            ->search($text, array_map(SupermarketId::fromString(...), $supermarkets), $page, $perPage);
    }

    /**
     * @return list<string>
     */
    private static function ids(ProductSearchPage $page): array
    {
        return array_map(static fn (ProductSearchResult $result): string => $result->productId, $page->results);
    }
}
