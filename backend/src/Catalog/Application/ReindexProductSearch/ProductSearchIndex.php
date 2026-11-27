<?php

declare(strict_types=1);

namespace App\Catalog\Application\ReindexProductSearch;

use App\Catalog\Application\SearchProducts\SearchUnavailable;

/**
 * The search index of the catalog (PD-100): derived data, rebuilt from the products of the database.
 */
interface ProductSearchIndex
{
    /**
     * Builds a new index with every product of the enabled supermarkets and the dictionary of PD-101, then switches
     * search to it, so search never sees a half-built index.
     *
     * @return int how many products were indexed
     *
     * @throws SearchUnavailable when the search service fails; the previous index is kept
     */
    public function rebuild(): int;

    /**
     * @return bool true when there is no index yet, or it has no product
     *
     * @throws SearchUnavailable
     */
    public function isEmpty(): bool;
}
