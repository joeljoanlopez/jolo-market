<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Http;

use App\Catalog\Infrastructure\Http\PoliteHttpClient;
use App\Tests\Support\FixedClock;
use App\Tests\Support\RecordingSleeper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PoliteHttpClientTest extends TestCase
{
    private FixedClock $clock;
    private RecordingSleeper $sleeper;

    protected function setUp(): void
    {
        $this->clock = FixedClock::at('2026-11-08 06:00:00');
        $this->sleeper = new RecordingSleeper($this->clock);
    }

    #[Test]
    public function it_does_not_wait_before_the_first_request(): void
    {
        $this->client()->request('GET', 'https://store.example/a');

        self::assertSame([], $this->sleeper->pausesInMilliseconds);
    }

    #[Test]
    public function it_waits_the_configured_delay_between_consecutive_requests(): void
    {
        $client = $this->client(delayInMilliseconds: 1000);

        $client->request('GET', 'https://store.example/a');
        $client->request('GET', 'https://store.example/b');

        self::assertSame([1000], $this->sleeper->pausesInMilliseconds);
    }

    #[Test]
    public function it_only_waits_for_what_is_left_of_the_delay(): void
    {
        $client = $this->client(delayInMilliseconds: 1000);

        $client->request('GET', 'https://store.example/a');
        $this->clock->advanceMicroseconds(400_000);
        $client->request('GET', 'https://store.example/b');

        self::assertSame([600], $this->sleeper->pausesInMilliseconds);
    }

    #[Test]
    public function it_does_not_wait_when_the_delay_has_already_passed(): void
    {
        $client = $this->client(delayInMilliseconds: 1000);

        $client->request('GET', 'https://store.example/a');
        $this->clock->advance(new \DateInterval('PT2S'));
        $client->request('GET', 'https://store.example/b');

        self::assertSame([], $this->sleeper->pausesInMilliseconds);
    }

    #[Test]
    public function it_keeps_the_delay_in_clients_created_with_other_options(): void
    {
        $client = $this->client(delayInMilliseconds: 1000);

        $client->request('GET', 'https://store.example/a');
        $client->withOptions(['headers' => ['Accept' => 'application/json']])->request('GET', 'https://store.example/b');

        self::assertSame([1000], $this->sleeper->pausesInMilliseconds);
    }

    private function client(int $delayInMilliseconds = 1000): PoliteHttpClient
    {
        return new PoliteHttpClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse('ok')),
            $delayInMilliseconds,
            $this->clock,
            $this->sleeper,
        );
    }
}
