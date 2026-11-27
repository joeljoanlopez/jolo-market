<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\FindUnmappedCategories\CategoryCount;
use App\Catalog\Application\FindUnmappedCategories\StoredCategories;

final readonly class InMemoryStoredCategories implements StoredCategories
{
    /**
     * @param list<CategoryCount> $counts
     */
    public function __construct(private array $counts)
    {
    }

    public function counts(): array
    {
        return $this->counts;
    }
}
