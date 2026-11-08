<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Domain\Model\PriceSnapshot;
use App\Catalog\Domain\Port\PriceSnapshotRepository;

final class InMemoryPriceSnapshotRepository implements PriceSnapshotRepository
{
    /** @var list<PriceSnapshot> */
    private array $snapshots = [];

    /** @var list<int> size of every append call, in order */
    public private(set) array $batchSizes = [];

    public function append(PriceSnapshot ...$snapshots): void
    {
        $this->batchSizes[] = \count($snapshots);
        array_push($this->snapshots, ...array_values($snapshots));
    }

    /**
     * @return list<PriceSnapshot>
     */
    public function all(): array
    {
        return $this->snapshots;
    }
}
