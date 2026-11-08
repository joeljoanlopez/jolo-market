<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

interface IngestionRunRepository
{
    /**
     * Inserts the run or updates it when it already exists.
     */
    public function save(IngestionRun $run): void;
}
