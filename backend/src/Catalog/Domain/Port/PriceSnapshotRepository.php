<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Port;

use App\Catalog\Domain\Model\PriceSnapshot;

interface PriceSnapshotRepository
{
    /**
     * Appends every snapshot in one go, so a run does not cost one write per product.
     */
    public function append(PriceSnapshot ...$snapshots): void;

    /**
     * Deletes the snapshots captured before $cutoff, except the latest snapshot of each product, which is kept however
     * old so an unavailable product still has its last known price (PD-46).
     *
     * @return int how many snapshots were deleted
     */
    public function deleteCapturedBefore(\DateTimeImmutable $cutoff): int;
}
