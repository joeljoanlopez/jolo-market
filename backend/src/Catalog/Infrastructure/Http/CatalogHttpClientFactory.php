<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Shared\Domain\Clock;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the HTTP client every supermarket adapter uses: identifying User-Agent, timeouts,
 * a polite delay between requests and retries with exponential backoff on 429 and 5xx.
 * A Retry-After header from the store takes precedence over the backoff.
 */
final class CatalogHttpClientFactory
{
    private const array RETRYABLE_STATUS_CODES = [429, 500, 502, 503, 504];

    private const float BACKOFF_MULTIPLIER = 2.0;

    public static function create(HttpClientInterface $client, CatalogHttpOptions $options, Clock $clock, Sleeper $sleeper): HttpClientInterface
    {
        $configured = $client->withOptions([
            'headers' => ['User-Agent' => $options->userAgent],
            'timeout' => $options->timeoutInSeconds,
            'max_duration' => $options->maxDurationInSeconds,
        ]);

        // Retries go through the polite client too, so they also respect the delay.
        return new RetryableHttpClient(
            new PoliteHttpClient($configured, $options->delayBetweenRequestsInMilliseconds, $clock, $sleeper),
            new GenericRetryStrategy(self::RETRYABLE_STATUS_CODES, $options->firstRetryDelayInMilliseconds, self::BACKOFF_MULTIPLIER),
            $options->maxRetries,
        );
    }
}
