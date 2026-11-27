<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use PHPUnit\Framework\Attributes\Test;

final class ProductTableTest extends DbalTestCase
{
    #[Test]
    public function it_has_no_full_text_index_since_search_runs_on_typesense(): void
    {
        $fullTextIndexes = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND INDEX_TYPE = 'FULLTEXT'",
        );

        self::assertSame([], $fullTextIndexes);
    }
}
