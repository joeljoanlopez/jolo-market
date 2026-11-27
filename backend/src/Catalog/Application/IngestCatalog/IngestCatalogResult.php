<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

final readonly class IngestCatalogResult
{
    /**
     * @param list<IngestionRunSummary> $runs
     * @param string|null               $searchIndexError why the search index could not be rebuilt after the runs
     */
    public function __construct(public array $runs, public ?string $searchIndexError = null)
    {
    }

    /**
     * Every run succeeded and the search index reflects them.
     */
    public function succeeded(): bool
    {
        return $this->allSucceeded() && null === $this->searchIndexError;
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
