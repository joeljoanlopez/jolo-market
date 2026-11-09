<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Source\Aldi;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Infrastructure\Source\Aldi\AldiCatalogSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Runs against pages recorded from www.aldi.es on 2026-11-09 (tests/Fixtures/Aldi), trimmed to
 * their __NEXT_DATA__ block, a few sub-categories and a few products. No network is involved.
 */
final class AldiCatalogSourceTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../../../Fixtures/Aldi/';

    /** @var list<string> */
    private array $requestedUrls = [];

    #[Test]
    public function it_identifies_itself_as_aldi(): void
    {
        self::assertSame('aldi', $this->source()->supermarketId()->value());
    }

    #[Test]
    public function it_walks_the_category_tree_from_the_products_page(): void
    {
        $this->fetch();

        self::assertSame([
            'https://www.aldi.es/productos.html',
            'https://www.aldi.es/productos/fruta-y-verdura.html',
            'https://www.aldi.es/productos/despensa.html',
            'https://www.aldi.es/productos/marcas.html',
            'https://www.aldi.es/productos/fruta-y-verdura/fruta.html',
            'https://www.aldi.es/productos/despensa/conservas-de-pescado.html',
            'https://www.aldi.es/productos/marcas/golden-seafood.html',
        ], $this->requestedUrls);
    }

    #[Test]
    public function it_returns_each_product_once_even_when_it_is_in_several_categories(): void
    {
        $ids = array_map(static fn (SourceProduct $product): string => $product->externalId, $this->fetch());

        self::assertSame(['996300', '996000', '887900', '662600', '644000', '601746900', '996900'], $ids);
    }

    #[Test]
    public function it_maps_a_product_sold_by_size(): void
    {
        self::assertEquals(
            new SourceProduct(
                externalId: '601746900',
                name: 'Filete de atún ahumado',
                priceCents: 275,
                packaging: '160 g',
                brand: 'GOLDEN SEAFOOD',
                categoryPath: ['Despensa', 'Conservas de pescado'],
                imageUrl: 'https://s7g10.scene7.com/is/image/aldinord/6017469-1',
            ),
            $this->product('601746900'),
        );
    }

    #[Test]
    public function it_takes_the_category_from_the_product_and_not_from_the_page_it_was_found_on(): void
    {
        // Found under "Marcas > Golden Seafood", but it is a frozen product.
        self::assertSame(['Congelados', 'Pescado y marisco congelado'], $this->product('996900')->categoryPath);
    }

    #[Test]
    public function it_sizes_canned_food_by_its_drained_weight(): void
    {
        self::assertSame('63 g', $this->product('662600')->packaging);
        self::assertSame('pack de 2 x 62 g', $this->product('887900')->packaging);
        self::assertSame('pack 3 x 60 g', $this->product('644000')->packaging);
    }

    #[Test]
    public function it_prices_a_loose_product_by_the_reference_unit_price(): void
    {
        $lemon = $this->product('996300');

        self::assertNull($lemon->packaging);
        self::assertSame(179, $lemon->priceCents);
        self::assertSame(179, $lemon->referenceUnitPriceCents);
        self::assertSame('kg', $lemon->referenceUnit);
    }

    #[Test]
    public function it_passes_no_reference_price_for_an_exact_size(): void
    {
        // Aldi gives 49.05 €/kg, but the size (63 g) is exact, so we compute the unit price (PD-29).
        $cockles = $this->product('662600');

        self::assertNull($cockles->referenceUnitPriceCents);
        self::assertNull($cockles->referenceUnit);
    }

    #[Test]
    public function it_leaves_the_price_out_when_the_store_publishes_none(): void
    {
        $organicLemon = $this->product('996000');

        self::assertNull($organicLemon->priceCents);
        self::assertNull($organicLemon->packaging);
        self::assertSame('BIO', $organicLemon->brand);
    }

    #[Test]
    public function it_leaves_out_an_image_url_that_is_not_a_url(): void
    {
        // Seen on 2026-11-09: ".../6010588_pack_2_0_es_es_aceite_micelar_2023_cw29 ID".
        $page = str_replace('aldinord/6017469-1"', 'aldinord/6017469-1 ID"', (string) file_get_contents(self::FIXTURES.'despensa--conservas-de-pescado.html'));

        $products = $this->fetch(['despensa--conservas-de-pescado.html' => $page]);
        $tuna = array_values(array_filter($products, static fn (SourceProduct $product): bool => '601746900' === $product->externalId))[0];

        self::assertNull($tuna->imageUrl);
        self::assertSame(275, $tuna->priceCents);
    }

    #[Test]
    public function it_fails_when_a_page_has_no_page_data(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('https://www.aldi.es/productos/despensa.html has no page data');

        $this->fetch(['despensa.html' => '<!DOCTYPE html><html><body>Maintenance</body></html>']);
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
    private function source(array $overrides = []): AldiCatalogSource
    {
        $this->requestedUrls = [];

        $client = new MockHttpClient(function (string $method, string $url) use ($overrides): MockResponse {
            $this->requestedUrls[] = $url;

            // https://www.aldi.es/productos/despensa/conservas-de-pescado.html => despensa--conservas-de-pescado.html
            $path = (string) preg_replace('#^https://www\.aldi\.es/productos/?#', '', $url);
            $fixture = '.html' === $path ? 'productos.html' : str_replace('/', '--', $path);

            return new MockResponse($overrides[$fixture] ?? (string) file_get_contents(self::FIXTURES.$fixture));
        });

        return new AldiCatalogSource($client);
    }
}
