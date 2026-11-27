<?php

declare(strict_types=1);

namespace App\Catalog\Application\ReindexProductSearch;

use App\Catalog\Application\SearchProducts\SearchUnavailable;

/**
 * Rebuilds the search index from the catalog and reloads the dictionary (PD-100, PD-101).
 */
final readonly class ReindexProductSearchHandler
{
    public function __construct(private ProductSearchIndex $index)
    {
    }

    /**
     * @throws SearchUnavailable
     */
    public function __invoke(ReindexProductSearchCommand $command): ReindexProductSearchResult
    {
        if ($command->onlyIfEmpty && !$this->index->isEmpty()) {
            return new ReindexProductSearchResult(false, 0);
        }

        return new ReindexProductSearchResult(true, $this->index->rebuild());
    }
}
