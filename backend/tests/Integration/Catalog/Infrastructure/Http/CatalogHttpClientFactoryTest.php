<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Http;

use App\Catalog\Infrastructure\Http\CatalogHttpClientFactory;
use App\Catalog\Infrastructure\Http\CatalogHttpOptions;
use App\Tests\Support\FixedClock;
use App\Tests\Support\RecordingSleeper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Retry delays are zero here so no test sleeps; the delay between requests goes through a recording sleeper.
 */
final class CatalogHttpClientFactoryTest extends TestCase
{
    #[Test]
    public function it_sends_the_identifying_user_agent(): void
    {
        $response = new MockResponse('ok');

        $this->client([$response])->request('GET', 'https://store.example/a')->getContent();

        $headers = $response->getRequestOptions()['headers'];
        self::assertIsArray($headers);
        self::assertContains('User-Agent: jolo-market/1.0 (+https://github.com/joeljoanlopez/jolo-market)', $headers);
    }

    #[Test]
    public function it_applies_the_timeouts(): void
    {
        $response = new MockResponse('ok');

        $this->client([$response])->request('GET', 'https://store.example/a')->getContent();

        self::assertSame(10.0, $response->getRequestOptions()['timeout']);
        self::assertSame(30.0, $response->getRequestOptions()['max_duration']);
    }

    #[Test]
    public function it_retries_a_server_error(): void
    {
        $client = $this->client([new MockResponse('', ['http_code' => 503]), new MockResponse('catalog')]);

        self::assertSame('catalog', $client->request('GET', 'https://store.example/a')->getContent());
    }

    #[Test]
    public function it_retries_when_rate_limited(): void
    {
        $client = $this->client([new MockResponse('', ['http_code' => 429]), new MockResponse('catalog')]);

        self::assertSame('catalog', $client->request('GET', 'https://store.example/a')->getContent());
    }

    #[Test]
    public function it_gives_up_after_the_maximum_number_of_retries(): void
    {
        $responses = array_map(static fn (): MockResponse => new MockResponse('', ['http_code' => 500]), range(1, 4));
        $responses[] = new MockResponse('too late');

        $response = $this->client($responses)->request('GET', 'https://store.example/a');

        self::assertSame(500, $response->getStatusCode());
    }

    #[Test]
    public function it_does_not_retry_a_client_error(): void
    {
        $client = $this->client([new MockResponse('', ['http_code' => 404]), new MockResponse('catalog')]);

        self::assertSame(404, $client->request('GET', 'https://store.example/a')->getStatusCode());
    }

    #[Test]
    public function it_waits_between_requests(): void
    {
        $clock = FixedClock::at('2026-11-08 06:00:00');
        $sleeper = new RecordingSleeper($clock);
        $client = $this->client([new MockResponse('a'), new MockResponse('b')], $clock, $sleeper);

        $client->request('GET', 'https://store.example/a')->getContent();
        $client->request('GET', 'https://store.example/b')->getContent();

        self::assertSame([1000], $sleeper->pausesInMilliseconds);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function client(array $responses, ?FixedClock $clock = null, ?RecordingSleeper $sleeper = null): HttpClientInterface
    {
        $clock ??= FixedClock::at('2026-11-08 06:00:00');

        return CatalogHttpClientFactory::create(
            new MockHttpClient($responses),
            new CatalogHttpOptions(
                userAgent: 'jolo-market/1.0 (+https://github.com/joeljoanlopez/jolo-market)',
                delayBetweenRequestsInMilliseconds: 1000,
                maxRetries: 3,
                firstRetryDelayInMilliseconds: 0,
                timeoutInSeconds: 10.0,
                maxDurationInSeconds: 30.0,
            ),
            $clock,
            $sleeper ?? new RecordingSleeper($clock),
        );
    }
}
