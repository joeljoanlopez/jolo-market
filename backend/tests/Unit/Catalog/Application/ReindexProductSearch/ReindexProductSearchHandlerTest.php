<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\ReindexProductSearch;

use App\Catalog\Application\ReindexProductSearch\ReindexProductSearchCommand;
use App\Catalog\Application\ReindexProductSearch\ReindexProductSearchHandler;
use App\Catalog\Application\SearchProducts\SearchUnavailable;
use App\Tests\Support\Catalog\InMemoryProductSearchIndex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReindexProductSearchHandlerTest extends TestCase
{
    #[Test]
    public function it_rebuilds_the_index_and_tells_how_many_products_it_indexed(): void
    {
        $index = new InMemoryProductSearchIndex(products: 42);

        $result = new ReindexProductSearchHandler($index)(new ReindexProductSearchCommand());

        self::assertSame(1, $index->rebuilds);
        self::assertTrue($result->rebuilt);
        self::assertSame(42, $result->indexed);
    }

    #[Test]
    public function only_if_empty_it_leaves_an_index_with_products_as_it_is(): void
    {
        $index = new InMemoryProductSearchIndex(products: 42);
        $index->rebuild();

        $result = new ReindexProductSearchHandler($index)(new ReindexProductSearchCommand(onlyIfEmpty: true));

        self::assertSame(1, $index->rebuilds);
        self::assertFalse($result->rebuilt);
    }

    #[Test]
    public function only_if_empty_it_builds_a_missing_or_empty_index(): void
    {
        $index = new InMemoryProductSearchIndex(products: 3);

        $result = new ReindexProductSearchHandler($index)(new ReindexProductSearchCommand(onlyIfEmpty: true));

        self::assertSame(1, $index->rebuilds);
        self::assertTrue($result->rebuilt);
        self::assertSame(3, $result->indexed);
    }

    #[Test]
    public function it_lets_a_search_failure_through(): void
    {
        $this->expectException(SearchUnavailable::class);

        new ReindexProductSearchHandler(new InMemoryProductSearchIndex(failing: true))(new ReindexProductSearchCommand());
    }
}
