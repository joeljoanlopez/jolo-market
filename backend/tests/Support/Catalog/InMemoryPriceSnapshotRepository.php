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

    public function deleteCapturedBefore(\DateTimeImmutable $cutoff): int
    {
        $latest = [];
        foreach ($this->snapshots as $snapshot) {
            $id = $snapshot->productId()->value();
            if (!isset($latest[$id]) || $snapshot->capturedAt() > $latest[$id]) {
                $latest[$id] = $snapshot->capturedAt();
            }
        }

        $kept = array_values(array_filter(
            $this->snapshots,
            static fn (PriceSnapshot $snapshot): bool => $snapshot->capturedAt() >= $cutoff
                || $snapshot->capturedAt() == $latest[$snapshot->productId()->value()],
        ));
        $deleted = \count($this->snapshots) - \count($kept);
        $this->snapshots = $kept;

        return $deleted;
    }
}
