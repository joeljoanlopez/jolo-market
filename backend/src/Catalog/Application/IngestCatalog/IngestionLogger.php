<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * What ingestion reports while it runs, for operators (E1.22). Implemented with structured logs.
 */
interface IngestionLogger
{
    /**
     * A malformed product was left out of the run (PD-23).
     */
    public function productSkipped(SupermarketId $supermarketId, string $externalId, string $reason): void;

    public function runFinished(IngestionRun $run): void;

    /**
     * The search index could not be rebuilt after the runs; the catalog stays ingested (PD-100).
     */
    public function searchIndexFailed(string $reason): void;
}
