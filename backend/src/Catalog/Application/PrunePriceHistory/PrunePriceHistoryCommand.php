<?php

declare(strict_types=1);

namespace App\Catalog\Application\PrunePriceHistory;

final readonly class PrunePriceHistoryCommand
{
    /**
     * @param int $retentionMonths snapshots older than this many months are deleted (PD-46)
     */
    public function __construct(public int $retentionMonths)
    {
        if ($retentionMonths < 1) {
            throw InvalidRetentionPeriod::belowOneMonth($retentionMonths);
        }
    }
}
