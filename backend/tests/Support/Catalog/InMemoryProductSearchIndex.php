<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\ReindexProductSearch\ProductSearchIndex;
use App\Catalog\Application\SearchProducts\SearchUnavailable;

/**
 * Indexes a fixed number of products, or fails like an unreachable Typesense.
 */
final class InMemoryProductSearchIndex implements ProductSearchIndex
{
    public int $rebuilds = 0;

    private int $indexed = 0;

    public function __construct(private readonly int $products = 0, private bool $failing = false)
    {
    }

    public function failing(): void
    {
        $this->failing = true;
    }

    public function rebuild(): int
    {
        if ($this->failing) {
            throw SearchUnavailable::because('the fake index is failing.');
        }
        ++$this->rebuilds;
        $this->indexed = $this->products;

        return $this->indexed;
    }

    public function isEmpty(): bool
    {
        if ($this->failing) {
            throw SearchUnavailable::because('the fake index is failing.');
        }

        return 0 === $this->indexed;
    }
}
