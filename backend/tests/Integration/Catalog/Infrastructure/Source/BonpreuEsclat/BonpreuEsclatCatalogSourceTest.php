<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Source\BonpreuEsclat;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Infrastructure\Source\BonpreuEsclat\BonpreuEsclatCatalogSource;
use App\Tests\Support\FixedClock;
use App\Tests\Support\RecordingSleeper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Runs against responses recorded from www.compraonline.bonpreuesclat.cat on 2026-11-09
 * (tests/Fixtures/BonpreuEsclat), trimmed to two top categories, a few products and three pages.
 * The CSRF token is a placeholder. No network is involved.
 */
final class BonpreuEsclatCatalogSourceTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../../../Fixtures/BonpreuEsclat/';

    private const string STORE = 'https://www.compraonline.bonpreuesclat.cat';

    private const string FRESH_CATEGORY = 'c95cfbf2-501d-433f-bae3-10fcef330b11';

    private const string DAIRY_CATEGORY = '8e6bb6f8-67ac-4a57-8260-c861830774f0';

    private const string PAGE = self::STORE.'/api/webproductpagews/v6/product-pages?maxProductsToDecorate=1000&maxPageSize=1000&tag=web&tag=category-item&';

    /** @var list<array{url: string, headers: array<string, string>}> */
    private array $requests = [];

    private RecordingSleeper $sleeper;

    #[Test]
    public function it_identifies_itself_as_bonpreu(): void
    {
        self::assertSame('bonpreu', $this->source()->supermarketId()->value());
    }

    #[Test]
    public function it_opens_a_session_then_reads_every_page_of_every_top_category(): void
    {
        $this->fetch();

        self::assertSame([
            self::STORE.'/',
            self::STORE.'/api/webproductpagews/v1/categories?decoration=false&categoryDepth=4',
            self::PAGE.'includeAdditionalPageInfo=true&categoryId='.self::FRESH_CATEGORY,
            self::PAGE.'includeAdditionalPageInfo=true&categoryId='.self::DAIRY_CATEGORY,
            self::PAGE.'pageToken=56be485f-cc17-4332-ba76-52df35e44164&categoryId='.self::DAIRY_CATEGORY,
        ], array_column($this->requests, 'url'));
    }

    #[Test]
    public function it_waits_90_seconds_between_api_requests_to_stay_under_the_firewall_allowance(): void
    {
        // The store's WAF challenges the seventh request within about five minutes (PD-32).
        $this->fetch();

        self::assertSame([90_000, 90_000, 90_000], $this->sleeper->pausesInMilliseconds);
    }

    #[Test]
    public function it_waits_six_minutes_and_retries_a_request_the_firewall_challenged(): void
    {
        $ids = array_map(
            static fn (SourceProduct $product): string => $product->externalId,
            $this->fetch(['frescos-page-1.json' => [self::challenge(), (string) file_get_contents(self::FIXTURES.'frescos-page-1.json')]]),
        );

        self::assertSame([90_000, 360_000, 90_000, 90_000], $this->sleeper->pausesInMilliseconds);
        self::assertSame(['01811', '90041'], \array_slice($ids, 0, 2));
        self::assertCount(2, array_filter($this->requests, static fn (array $request): bool => str_contains($request['url'], self::FRESH_CATEGORY)));
    }

    #[Test]
    public function it_fails_with_a_clear_message_when_the_firewall_keeps_challenging_a_request(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('firewall challenged');

        try {
            $this->fetch(['frescos-page-1.json' => [self::challenge(), self::challenge(), self::challenge()]]);
        } finally {
            self::assertSame([90_000, 360_000, 360_000], $this->sleeper->pausesInMilliseconds);
        }
    }

    #[Test]
    public function it_sends_a_browser_user_agent_on_every_request(): void
    {
        $this->fetch();

        foreach ($this->requests as $request) {
            self::assertStringStartsWith('Mozilla/5.0', $request['headers']['user-agent'] ?? '');
        }
    }

    #[Test]
    public function it_sends_the_session_cookies_and_csrf_token_to_the_api(): void
    {
        $this->fetch();

        foreach (\array_slice($this->requests, 1) as $request) {
            self::assertSame('0f6c2a4e-8d1b-4c3a-9e7f-5b2d1a0c9e88', $request['headers']['x-csrf-token'] ?? null);
            self::assertSame('VISITORID=visitor-1; aws-waf-token=waf-1', $request['headers']['cookie'] ?? null);
            self::assertSame('web', $request['headers']['ecom-request-source'] ?? null);
        }
    }

    #[Test]
    public function it_returns_each_product_once_even_when_a_page_lists_it_twice(): void
    {
        // 44787 is both a featured product and an offer on the first page.
        $ids = array_map(static fn (SourceProduct $product): string => $product->externalId, $this->fetch());

        self::assertSame(['01811', '90041', '44787', '72316', '22338', '38974', '25069', '66984'], $ids);
    }

    #[Test]
    public function it_maps_a_product(): void
    {
        self::assertEquals(
            new SourceProduct(
                externalId: '25069',
                name: 'ATO NATURA Llet sencera 6x1L en cartró',
                priceCents: 768,
                packaging: '6 x 1L',
                brand: 'ATO NATURA',
                categoryPath: ['Làctics i ous', 'Llets i begudes vegetals', 'Packs'],
                imageUrl: 'https://www.compraonline.bonpreuesclat.cat/images-v3/dcbcfd72-cf23-44a2-8e14-8a38edd645a3/67c84777-2b9e-46cb-ad2b-9ab8c964923f/300x300.jpg',
            ),
            $this->product('25069'),
        );
    }

    #[Test]
    public function it_prices_a_product_sold_by_variable_weight_by_its_price_per_kg(): void
    {
        // "150g - 400g": the store charges the typical weight; the size is approximate (PD-29).
        self::assertEquals(
            new SourceProduct(
                externalId: '01811',
                name: 'Pera Conference extra 1 u.',
                priceCents: 90,
                packaging: null,
                brand: null,
                categoryPath: ['Frescos', 'Fruites i verdures', 'De temporada', 'Fruita'],
                imageUrl: 'https://www.compraonline.bonpreuesclat.cat/images-v3/dcbcfd72-cf23-44a2-8e14-8a38edd645a3/aa48f34b-9117-4ad6-8834-c520321c2644/300x300.jpg',
                referenceUnitPriceCents: 329,
                referenceUnit: 'kg',
            ),
            $this->product('01811'),
        );
    }

    #[Test]
    public function it_prices_a_product_sold_by_the_piece_without_a_size_per_unit(): void
    {
        $apple = $this->product('90041');

        self::assertNull($apple->packaging);
        self::assertSame(59, $apple->referenceUnitPriceCents);
        self::assertSame('unit', $apple->referenceUnit);
    }

    #[Test]
    public function it_leaves_the_reference_price_out_when_the_size_is_known(): void
    {
        $milk = $this->product('25069');

        self::assertNull($milk->referenceUnitPriceCents);
        self::assertNull($milk->referenceUnit);
    }

    #[Test]
    public function it_files_a_product_listed_under_a_promotion_in_the_category_being_read(): void
    {
        // Bonpreu gives "Ofertes > Preu rebaixat" for this milk while it is on offer.
        self::assertSame(['Làctics i ous'], $this->product('22338')->categoryPath);
    }

    #[Test]
    public function it_counts_items_sold_per_pack_as_units(): void
    {
        self::assertSame('12 ud', $this->product('38974')->packaging);
        self::assertSame('8 x 1 ud', $this->product('72316')->packaging);
    }

    #[Test]
    public function it_leaves_the_price_out_when_it_cannot_be_read(): void
    {
        $page = str_replace('"amount": "7.68"', '"amount": "n/a"', (string) file_get_contents(self::FIXTURES.'lactics-page-1.json'));

        $products = $this->fetch(['lactics-page-1.json' => $page]);
        $milk = array_values(array_filter($products, static fn (SourceProduct $product): bool => '25069' === $product->externalId))[0];

        self::assertNull($milk->priceCents);
    }

    #[Test]
    public function it_fails_when_the_store_gives_no_csrf_token(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('did not return a CSRF token');

        $this->fetch(['home.html' => '<!DOCTYPE html><html><body>Request blocked</body></html>']);
    }

    /**
     * @param array<string, string|MockResponse|list<string|MockResponse>> $overrides fixture file name => body or
     *                                                                                response, or one per request in turn
     *
     * @return list<SourceProduct>
     */
    private function fetch(array $overrides = []): array
    {
        return array_values([...$this->source($overrides)->fetchCatalog(PostalCode::fromString('08001'))]);
    }

    private function product(string $externalId): SourceProduct
    {
        foreach ($this->fetch() as $product) {
            if ($product->externalId === $externalId) {
                return $product;
            }
        }

        self::fail(\sprintf('Product %s was not returned.', $externalId));
    }

    private static function challenge(): MockResponse
    {
        return new MockResponse('', ['http_code' => 202, 'response_headers' => ['x-amzn-waf-action: challenge']]);
    }

    /**
     * @param array<string, string|MockResponse|list<string|MockResponse>> $overrides
     */
    private function source(array $overrides = []): BonpreuEsclatCatalogSource
    {
        $this->requests = [];
        $this->sleeper = new RecordingSleeper(FixedClock::at('2026-11-09 10:00:00'));
        // Every fixture becomes a queue of responses: a list override answers one request each, in turn.
        $queues = array_map(static fn (string|MockResponse|array $override): array => \is_array($override) ? $override : [$override], $overrides);
        $fixture = static function (string $name) use (&$queues): MockResponse {
            $override = isset($queues[$name]) ? (\count($queues[$name]) > 1 ? array_shift($queues[$name]) : $queues[$name][0]) : (string) file_get_contents(self::FIXTURES.$name);

            return $override instanceof MockResponse ? $override : new MockResponse($override);
        };

        $home = $overrides['home.html'] ?? (string) file_get_contents(self::FIXTURES.'home.html');
        $homeBody = \is_string($home) ? $home : '';

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($fixture, $homeBody): MockResponse {
            // The client hands the mock its headers as "Name: value" lines.
            $headers = [];
            $lines = \is_array($options['headers'] ?? null) ? $options['headers'] : [];
            foreach (array_filter($lines, \is_string(...)) as $line) {
                [$name, $value] = explode(': ', $line, 2) + [1 => ''];
                $headers[strtolower($name)] = $value;
            }
            $this->requests[] = ['url' => $url, 'headers' => $headers];

            if (self::STORE.'/' === $url) {
                return new MockResponse($homeBody, ['response_headers' => [
                    'Set-Cookie: VISITORID=visitor-1; Max-Age=7884000; Path=/; Secure; HttpOnly',
                    'Set-Cookie: aws-waf-token=waf-1; Path=/; Secure',
                ]]);
            }
            if (str_contains($url, '/v1/categories')) {
                return $fixture('categories.json');
            }
            if (str_contains($url, self::FRESH_CATEGORY)) {
                return $fixture('frescos-page-1.json');
            }

            return $fixture(str_contains($url, 'pageToken=') ? 'lactics-page-2.json' : 'lactics-page-1.json');
        });

        return new BonpreuEsclatCatalogSource($client, $this->sleeper);
    }
}
