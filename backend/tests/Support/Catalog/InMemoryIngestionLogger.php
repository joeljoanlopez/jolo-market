<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\IngestCatalog\IngestionLogger;
use App\Catalog\Application\IngestCatalog\IngestionRun;
use App\Catalog\Domain\ValueObject\SupermarketId;

final class InMemoryIngestionLogger implements IngestionLogger
{
    /** @var list<array{supermarket: string, externalId: string, reason: string}> */
    public private(set) array $skippedProducts = [];

    /** @var list<IngestionRun> */
    public private(set) array $finishedRuns = [];

    public function productSkipped(SupermarketId $supermarketId, string $externalId, string $reason): void
    {
        $this->skippedProducts[] = ['supermarket' => $supermarketId->value(), 'externalId' => $externalId, 'reason' => $reason];
    }

    public function runFinished(IngestionRun $run): void
    {
        $this->finishedRuns[] = clone $run;
    }
}
