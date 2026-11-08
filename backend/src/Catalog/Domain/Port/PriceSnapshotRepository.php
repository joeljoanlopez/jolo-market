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
}
