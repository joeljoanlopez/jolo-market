<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

final readonly class IngestCatalogCommand
{
    /**
     * @param string|null $supermarket slug of the only supermarket to ingest; null ingests every enabled one
     */
    public function __construct(public ?string $supermarket = null)
    {
    }
}
