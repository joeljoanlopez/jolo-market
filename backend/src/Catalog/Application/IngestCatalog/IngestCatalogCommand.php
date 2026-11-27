<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

final readonly class IngestCatalogCommand
{
    /**
     * @param string|null $supermarket        slug of the only supermarket to ingest; null ingests every enabled one
     * @param bool        $rebuildSearchIndex rebuild the search index after the runs (PD-100); the backfill of the
     *                                        development catalog rebuilds it once at the end instead of every day
     */
    public function __construct(public ?string $supermarket = null, public bool $rebuildSearchIndex = true)
    {
    }
}
