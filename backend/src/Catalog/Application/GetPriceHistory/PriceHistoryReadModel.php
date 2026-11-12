<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetPriceHistory;

use App\Catalog\Domain\ValueObject\ProductId;

interface PriceHistoryReadModel
{
    /**
     * The snapshots of the product captured at or after $from and before $before (each bound only when given).
     *
     * @return list<PriceHistoryPoint>
     */
    public function pointsOf(ProductId $id, ?\DateTimeImmutable $from, ?\DateTimeImmutable $before): array;
}
