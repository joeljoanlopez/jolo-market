<?php

declare(strict_types=1);

namespace App\Catalog\Application\PrunePriceHistory;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidRetentionPeriod extends InvalidArgument
{
    public static function belowOneMonth(int $months): self
    {
        return new self(\sprintf('The price history retention must be at least 1 month, got %d.', $months));
    }
}
