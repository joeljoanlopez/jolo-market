<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\SearchProducts\ProductSearchResult;
use App\Catalog\Application\SearchProducts\SearchProductsHandler;
use App\Catalog\Application\SearchProducts\SearchProductsQuery;
use PHPUnit\Framework\Attributes\Test;

final class SearchProductsWiringTest extends DbalTestCase
{
    #[Test]
    public function it_searches_the_database_among_the_supermarkets_enabled_in_the_environment(): void
    {
        // The test environment enables mercadona and aldi only.
        $insert = 'INSERT INTO product (id, supermarket_id, external_id, name, price_cents, available, last_seen_at)
                   VALUES (?, ?, ?, ?, 99, 1, ?)';
        $this->connection->executeStatement($insert, [random_bytes(16), 'mercadona', '1', 'Leche entera', '2026-11-08 06:00:00']);
        $this->connection->executeStatement($insert, [random_bytes(16), 'bonpreu', '1', 'Leche entera', '2026-11-08 06:00:00']);
        $handler = self::getContainer()->get('test.catalog.search_products_handler');
        self::assertInstanceOf(SearchProductsHandler::class, $handler);

        $page = $handler(new SearchProductsQuery('leche'));

        self::assertSame(['mercadona'], array_map(static fn (ProductSearchResult $result): string => $result->supermarketId, $page->results));
    }
}
