<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\SearchProducts;

use App\Catalog\Application\IngestCatalog\SupermarketNotEnabled;
use App\Catalog\Application\SearchProducts\InvalidSearchProductsQuery;
use App\Catalog\Application\SearchProducts\ProductSearchPage;
use App\Catalog\Application\SearchProducts\ProductSearchResult;
use App\Catalog\Application\SearchProducts\SearchProductsHandler;
use App\Catalog\Application\SearchProducts\SearchProductsQuery;
use App\Tests\Support\Catalog\FixedEnabledSupermarkets;
use App\Tests\Support\Catalog\InMemoryProductSearchReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchProductsHandlerTest extends TestCase
{
    private InMemoryProductSearchReadModel $readModel;

    protected function setUp(): void
    {
        $this->readModel = new InMemoryProductSearchReadModel();
    }

    #[Test]
    public function it_returns_the_matching_products_of_every_enabled_supermarket(): void
    {
        $this->readModel->add(self::product('1', 'Leche entera', 'mercadona'));
        $this->readModel->add(self::product('2', 'Leche desnatada', 'aldi'));
        $this->readModel->add(self::product('3', 'Pan de molde', 'mercadona'));

        $page = $this->search(new SearchProductsQuery('leche'), enabled: ['mercadona', 'aldi']);

        self::assertSame(['1', '2'], self::ids($page->results));
    }

    #[Test]
    public function it_does_not_search_a_supermarket_that_is_not_enabled(): void
    {
        $this->readModel->add(self::product('1', 'Leche entera', 'mercadona'));
        $this->readModel->add(self::product('2', 'Leche entera', 'bonpreu'));

        $page = $this->search(new SearchProductsQuery('leche'), enabled: ['mercadona']);

        self::assertSame(['1'], self::ids($page->results));
    }

    #[Test]
    public function it_searches_only_the_supermarket_of_the_filter(): void
    {
        $this->readModel->add(self::product('1', 'Leche entera', 'mercadona'));
        $this->readModel->add(self::product('2', 'Leche entera', 'aldi'));

        $page = $this->search(new SearchProductsQuery('leche', supermarket: 'aldi'), enabled: ['mercadona', 'aldi']);

        self::assertSame(['2'], self::ids($page->results));
    }

    #[Test]
    public function it_rejects_a_filter_on_a_supermarket_that_is_not_enabled(): void
    {
        $this->expectException(SupermarketNotEnabled::class);

        $this->search(new SearchProductsQuery('leche', supermarket: 'bonpreu'), enabled: ['mercadona']);
    }

    #[Test]
    public function it_returns_the_requested_page_with_the_total_of_the_whole_query(): void
    {
        foreach (['1', '2', '3', '4', '5'] as $id) {
            $this->readModel->add(self::product($id, 'Leche '.$id, 'mercadona'));
        }

        $page = $this->search(new SearchProductsQuery('leche', page: 2, perPage: 2), enabled: ['mercadona']);

        self::assertSame(['3', '4'], self::ids($page->results));
        self::assertSame(5, $page->total);
        self::assertSame(2, $page->page);
        self::assertSame(2, $page->perPage);
    }

    #[Test]
    public function it_returns_the_cheapest_unit_price_of_each_unit_over_the_whole_query(): void
    {
        $this->readModel->add(self::product('1', 'Leche entera', 'mercadona', unitPriceCents: 99, unit: 'l'));
        $this->readModel->add(self::product('2', 'Leche en polvo', 'mercadona', unitPriceCents: 1200, unit: 'kg'));
        $this->readModel->add(self::product('3', 'Leche semi', 'mercadona', unitPriceCents: 89, unit: 'l'));
        $this->readModel->add(self::product('4', 'Leche sin precio', 'mercadona', unitPriceCents: null, unit: null));

        $page = $this->search(new SearchProductsQuery('leche', perPage: 1), enabled: ['mercadona']);

        self::assertSame(['l' => 89, 'kg' => 1200], $page->cheapestUnitPriceCentsByUnit);
    }

    #[Test]
    public function it_rejects_a_page_below_one(): void
    {
        $this->expectException(InvalidSearchProductsQuery::class);

        new SearchProductsQuery('leche', page: 0);
    }

    #[Test]
    public function it_rejects_a_page_size_below_one(): void
    {
        $this->expectException(InvalidSearchProductsQuery::class);

        new SearchProductsQuery('leche', perPage: 0);
    }

    /**
     * @param list<string> $enabled
     */
    private function search(SearchProductsQuery $query, array $enabled): ProductSearchPage
    {
        return (new SearchProductsHandler($this->readModel, new FixedEnabledSupermarkets($enabled)))($query);
    }

    /**
     * @param list<ProductSearchResult> $results
     *
     * @return list<string>
     */
    private static function ids(array $results): array
    {
        return array_map(static fn (ProductSearchResult $result): string => $result->productId, $results);
    }

    private static function product(
        string $id,
        string $name,
        string $supermarket,
        ?int $unitPriceCents = 99,
        ?string $unit = 'l',
    ): ProductSearchResult {
        return new ProductSearchResult(
            productId: $id,
            name: $name,
            brand: null,
            supermarketId: $supermarket,
            supermarketName: ucfirst($supermarket),
            packageQuantityThousandths: 1000,
            packageUnit: 'l',
            priceCents: 99,
            unitPriceCents: $unitPriceCents,
            unitPriceUnit: $unit,
            imageUrl: null,
        );
    }
}
