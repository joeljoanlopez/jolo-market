<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Shared\Domain\Clock;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Leaves at least a fixed delay between the start of two requests, so ingestion never
 * hammers a supermarket's store.
 */
final class PoliteHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    /**
     * Shared by every client created with withOptions(), so they all respect the same delay.
     *
     * @var \ArrayObject<string, \DateTimeImmutable>
     */
    private \ArrayObject $lastRequestAt;

    public function __construct(
        HttpClientInterface $client,
        private readonly int $delayInMilliseconds,
        private readonly Clock $clock,
        private readonly Sleeper $sleeper,
    ) {
        $this->client = $client;
        $this->lastRequestAt = new \ArrayObject();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $previous = $this->lastRequestAt['at'] ?? null;
        if (null !== $previous) {
            $elapsedInMilliseconds = intdiv((int) $this->clock->now()->format('Uu') - (int) $previous->format('Uu'), 1000);
            $remaining = $this->delayInMilliseconds - $elapsedInMilliseconds;
            if ($remaining > 0) {
                $this->sleeper->sleep($remaining);
            }
        }

        $this->lastRequestAt['at'] = $this->clock->now();

        return $this->client->request($method, $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }
}
