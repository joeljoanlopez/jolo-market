<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\IngestCatalog\IngestionRun;
use App\Catalog\Application\IngestCatalog\IngestionRunRepository;

final class InMemoryIngestionRunRepository implements IngestionRunRepository
{
    /** @var array<string, IngestionRun> */
    private array $runs = [];

    /** @var list<string> status of every save, in order */
    public private(set) array $savedStatuses = [];

    public function save(IngestionRun $run): void
    {
        $this->runs[$run->id()->value()] = clone $run;
        $this->savedStatuses[] = $run->status()->value;
    }

    /**
     * @return list<IngestionRun>
     */
    public function all(): array
    {
        return array_values($this->runs);
    }

    public function single(): IngestionRun
    {
        if (1 !== \count($this->runs)) {
            throw new \LogicException(\sprintf('Expected one run, found %d.', \count($this->runs)));
        }

        return array_values($this->runs)[0];
    }

    /**
     * The run that was started last.
     */
    public function latest(): IngestionRun
    {
        $runs = array_values($this->runs);

        return $runs[\count($runs) - 1] ?? throw new \LogicException('No run was saved.');
    }

    public function forSupermarket(string $supermarketId): IngestionRun
    {
        foreach ($this->runs as $run) {
            if ($run->supermarketId()->value() === $supermarketId) {
                return $run;
            }
        }

        throw new \LogicException(\sprintf('No run for "%s".', $supermarketId));
    }
}
