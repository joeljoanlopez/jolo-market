<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Search;

use App\Catalog\Application\ReindexProductSearch\ProductSearchIndex;
use App\Catalog\Application\SearchProducts\ProductSearchResult;
use App\Catalog\Application\SearchProducts\SearchProductsHandler;
use App\Catalog\Application\SearchProducts\SearchProductsQuery;
use App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal\DbalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SearchProductsWiringTest extends DbalTestCase
{
    #[Test]
    public function it_searches_the_index_with_the_shipped_dictionary_among_the_enabled_supermarkets(): void
    {
        // The test environment enables mercadona and aldi only.
        $insert = 'INSERT INTO product (id, supermarket_id, external_id, name, price_cents, available, last_seen_at)
                   VALUES (?, ?, ?, ?, 99, 1, ?)';
        $this->connection->executeStatement($insert, [random_bytes(16), 'mercadona', '1', 'Leche entera', '2026-11-08 06:00:00']);
        $this->connection->executeStatement($insert, [random_bytes(16), 'aldi', '1', 'Llet sencera', '2026-11-08 06:00:00']);
        $this->connection->executeStatement($insert, [random_bytes(16), 'bonpreu', '1', 'Llet sencera', '2026-11-08 06:00:00']);
        $index = self::getContainer()->get(ProductSearchIndex::class);
        self::assertInstanceOf(ProductSearchIndex::class, $index);
        $index->rebuild();
        $handler = self::getContainer()->get('test.catalog.search_products_handler');
        self::assertInstanceOf(SearchProductsHandler::class, $handler);

        $page = $handler(new SearchProductsQuery('leche'));

        $supermarkets = array_map(static fn (ProductSearchResult $result): string => $result->supermarketId, $page->results);
        sort($supermarkets);
        self::assertSame(['aldi', 'mercadona'], $supermarkets);
    }
}
