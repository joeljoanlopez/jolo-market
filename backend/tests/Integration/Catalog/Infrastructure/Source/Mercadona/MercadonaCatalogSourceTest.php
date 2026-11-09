<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Source\Mercadona;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Infrastructure\Source\Mercadona\MercadonaCatalogSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Runs against responses recorded from tienda.mercadona.es on 2026-11-08 (tests/Fixtures/Mercadona),
 * trimmed to a few products. No network is involved.
 */
final class MercadonaCatalogSourceTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../../../Fixtures/Mercadona/';

    /** @var list<array{method: string, url: string, body: string}> */
    private array $requests = [];

    #[Test]
    public function it_identifies_itself_as_mercadona(): void
    {
        self::assertSame('mercadona', $this->source()->supermarketId()->value());
    }

    #[Test]
    public function it_resolves_the_warehouse_of_the_postal_code_and_uses_it_for_every_category(): void
    {
        $this->fetch();

        self::assertSame('PUT', $this->requests[0]['method']);
        self::assertSame('https://tienda.mercadona.es/api/postal-codes/actions/change-pc/', $this->requests[0]['url']);
        self::assertSame('{"new_postal_code":"46001"}', $this->requests[0]['body']);
        $categoryUrls = array_column(\array_slice($this->requests, 2), 'url');
        self::assertSame([
            'https://tienda.mercadona.es/api/categories/112/?lang=es&wh=vlc1',
            'https://tienda.mercadona.es/api/categories/164/?lang=es&wh=vlc1',
            'https://tienda.mercadona.es/api/categories/27/?lang=es&wh=vlc1',
        ], $categoryUrls);
    }

    #[Test]
    public function it_returns_every_product_of_every_leaf_category(): void
    {
        $ids = array_map(static fn (SourceProduct $product): string => $product->externalId, $this->fetch());

        self::assertSame(['4241', '66518', '86866', '3819', '30501'], $ids);
    }

    #[Test]
    public function it_maps_a_product_sold_by_size(): void
    {
        self::assertEquals(
            new SourceProduct(
                externalId: '4241',
                name: 'Aceite de oliva 0,4º Hacendado',
                priceCents: 1675,
                packaging: '5 l',
                brand: null,
                categoryPath: ['Aceite, especias y salsas', 'Aceite, vinagre y sal', 'Aceite de oliva'],
                imageUrl: 'https://prod-mercadona.imgix.net/images/3b8cde7b3cb069ee0316029012cf8562.jpg?fit=crop&h=300&w=300',
            ),
            $this->product('4241'),
        );
    }

    #[Test]
    public function it_uses_the_total_size_of_a_pack(): void
    {
        self::assertSame('2.4 l', $this->product('86866')->packaging);
        self::assertSame(588, $this->product('86866')->priceCents);
    }

    #[Test]
    public function it_maps_a_size_with_decimals(): void
    {
        self::assertSame('0.33 l', $this->product('66518')->packaging);
    }

    #[Test]
    public function it_prices_an_approximate_size_by_the_reference_unit_price(): void
    {
        $banana = $this->product('3819');

        self::assertNull($banana->packaging);
        self::assertSame(51, $banana->priceCents);
        self::assertSame(320, $banana->referenceUnitPriceCents);
        self::assertSame('kg', $banana->referenceUnit);
    }

    #[Test]
    public function it_passes_no_reference_price_for_an_exact_size(): void
    {
        // Mandarinas: reference 2.354 €/kg, but its size (1.5 kg) is exact, so no reference is passed.
        $mandarins = $this->product('30501');

        self::assertSame('1.5 kg', $mandarins->packaging);
        self::assertNull($mandarins->referenceUnitPriceCents);
    }

    #[Test]
    public function it_leaves_the_price_out_when_it_cannot_be_read(): void
    {
        $category = str_replace('"unit_price": "16.75"', '"unit_price": "n/a"', (string) file_get_contents(self::FIXTURES.'category-112.json'));

        $products = $this->fetch(['category-112.json' => $category]);

        self::assertNull($products[0]->priceCents);
    }

    #[Test]
    public function it_fails_when_the_postal_code_has_no_warehouse(): void
    {
        $source = new MercadonaCatalogSource(new MockHttpClient([new MockResponse('{"warehouse_changed":false}')]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('did not return a warehouse');

        iterator_to_array($source->fetchCatalog(PostalCode::fromString('46001')), false);
    }

    /**
     * @param array<string, string> $overrides fixture file name => body
     *
     * @return list<SourceProduct>
     */
    private function fetch(array $overrides = []): array
    {
        return array_values([...$this->source($overrides)->fetchCatalog(PostalCode::fromString('46001'))]);
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

    /**
     * @param array<string, string> $overrides
     */
    private function source(array $overrides = []): MercadonaCatalogSource
    {
        $this->requests = [];
        $fixture = static fn (string $name): string => $overrides[$name] ?? (string) file_get_contents(self::FIXTURES.$name);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($fixture): MockResponse {
            $body = $options['body'] ?? '';
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => \is_string($body) ? $body : ''];

            if (str_contains($url, '/postal-codes/')) {
                return new MockResponse('{"warehouse_changed":false}', ['response_headers' => ['x-customer-wh' => 'vlc1']]);
            }
            if (1 === preg_match('#/api/categories/(\d+)/#', $url, $matches)) {
                return new MockResponse($fixture('category-'.$matches[1].'.json'));
            }

            return new MockResponse($fixture('categories.json'));
        });

        return new MercadonaCatalogSource($client);
    }
}
