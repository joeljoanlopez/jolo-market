<?php

declare(strict_types=1);

namespace App\Catalog\Application\ReindexProductSearch;

final readonly class ReindexProductSearchCommand
{
    /**
     * @param bool $onlyIfEmpty rebuild only a missing or empty index, as when the stack starts (PD-100)
     */
    public function __construct(public bool $onlyIfEmpty = false)
    {
    }
}
