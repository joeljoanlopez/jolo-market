<?php

declare(strict_types=1);

namespace App\Catalog\Application\PrunePriceHistory;

final readonly class PrunePriceHistoryResult
{
    public function __construct(public \DateTimeImmutable $cutoff, public int $deleted)
    {
    }
}
