<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

final readonly class IngestCatalogResult
{
    /**
     * @param list<IngestionRunSummary> $runs
     */
    public function __construct(public array $runs)
    {
    }

    public function allSucceeded(): bool
    {
        foreach ($this->runs as $run) {
            if (IngestionRunStatus::SUCCEEDED->value !== $run->status) {
                return false;
            }
        }

        return true;
    }
}
