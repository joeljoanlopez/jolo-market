<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

use App\Shared\Domain\Exception\Unavailable;

/**
 * The search service did not answer or failed (PD-105): a 503. The message is for the logs only.
 */
final class SearchUnavailable extends Unavailable
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self('Product search is unavailable: '.$reason, 0, $previous);
    }
}
