<?php

declare(strict_types=1);

namespace App\Catalog\Application\ReindexProductSearch;

final readonly class ReindexProductSearchResult
{
    /**
     * @param bool $rebuilt false when the index already had products and only a missing or empty one was asked for
     * @param int  $indexed products in the new index; 0 when it was not rebuilt
     */
    public function __construct(public bool $rebuilt, public int $indexed)
    {
    }
}
