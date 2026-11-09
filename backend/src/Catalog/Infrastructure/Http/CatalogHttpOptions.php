<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

/**
 * How supermarket adapters talk to the stores (E1.19, PD-26).
 */
final readonly class CatalogHttpOptions
{
    public function __construct(
        public string $userAgent,
        public int $delayBetweenRequestsInMilliseconds,
        public int $maxRetries,
        public int $firstRetryDelayInMilliseconds,
        public float $timeoutInSeconds,
        public float $maxDurationInSeconds,
    ) {
    }
}
