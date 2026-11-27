<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

/**
 * The search service did not answer or failed (PD-105). The message is for the logs only.
 */
final class SearchUnavailable extends \RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self('Product search is unavailable: '.$reason, 0, $previous);
    }
}
