<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Configuration;

use App\Catalog\Infrastructure\Configuration\SearchConfigurationCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchConfigurationCheckTest extends TestCase
{
    #[Test]
    public function it_accepts_an_http_url_and_an_api_key(): void
    {
        self::assertSame([], (new SearchConfigurationCheck('http://typesense:8108', 'secret'))->problems());
        self::assertSame([], (new SearchConfigurationCheck('https://search.example.com', 'secret'))->problems());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function missingValues(): iterable
    {
        yield 'unset' => [null];
        yield 'empty' => [''];
        yield 'blank' => ['  '];
    }

    #[Test]
    #[DataProvider('missingValues')]
    public function it_reports_a_missing_api_key(?string $apiKey): void
    {
        self::assertSame(
            ['TYPESENSE_API_KEY is missing: set the API key of the Typesense service, for example with openssl rand -hex 32.'],
            (new SearchConfigurationCheck('http://typesense:8108', $apiKey))->problems(),
        );
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function malformedUrls(): iterable
    {
        yield 'unset' => [null];
        yield 'empty' => [''];
        yield 'no scheme' => ['typesense:8108'];
        yield 'other scheme' => ['ftp://typesense:8108'];
        yield 'no host' => ['http://'];
        yield 'not a url' => ['not a url'];
    }

    #[Test]
    #[DataProvider('malformedUrls')]
    public function it_reports_a_malformed_url(?string $url): void
    {
        self::assertSame(
            [\sprintf('TYPESENSE_URL is invalid: "%s" is not an http or https URL, for example http://typesense:8108.', $url ?? '')],
            (new SearchConfigurationCheck($url, 'secret'))->problems(),
        );
    }

    #[Test]
    public function it_reports_both_problems_at_once(): void
    {
        self::assertCount(2, (new SearchConfigurationCheck('', ''))->problems());
    }
}
