<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Source\BonpreuEsclat;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Http\Sleeper;
use App\Catalog\Infrastructure\Source\DecimalCents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bonpreu i Esclat through the internal JSON API of compraonline.bonpreuesclat.cat, an Ocado
 * Smart Platform store (PD-32).
 *
 * The store's WAF only answers browsers, so this adapter sends a browser User-Agent instead of
 * the identifying one (PD-32 overrides PD-26 for Bonpreu). The home page gives the session
 * cookies and a CSRF token; then every top category is read in pages of 1000 products, about
 * 40 requests per run. The WAF challenges the seventh request within about five minutes, and a
 * challenge can only be passed by running its JavaScript, so the adapter waits 90 seconds between
 * API requests (a run takes about an hour) and, if a request is challenged anyway, waits for the
 * challenge to lift and tries again. The online store has a single price region
 * (Catalunya), so the postal code is not used.
 */
final readonly class BonpreuEsclatCatalogSource implements SupermarketCatalogSource
{
    private const string BASE_URL = 'https://www.compraonline.bonpreuesclat.cat';

    private const string BROWSER_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private const string CSRF_PATTERN = '/"csrf":\{"token":"([0-9a-f-]{36})"/';

    private const string PAGE_QUERY = 'maxProductsToDecorate=1000&maxPageSize=1000&tag=web&tag=category-item';

    /** Under the WAF allowance with room to spare: it challenges the seventh request in about five minutes. */
    private const int PAUSE_BETWEEN_API_REQUESTS_IN_MILLISECONDS = 90_000;

    /** A challenge lifts after about five minutes. */
    private const int PAUSE_AFTER_A_CHALLENGE_IN_MILLISECONDS = 360_000;

    private const int RETRIES_AFTER_A_CHALLENGE = 2;

    /** The store's unit price units that map to our reference units (PD-29). */
    private const array REFERENCE_UNITS = ['fop.price.per.kg' => 'kg', 'fop.price.per.litre' => 'l', 'fop.price.per.each' => 'unit'];

    public function __construct(
        #[Autowire(service: 'catalog.http_client')]
        private HttpClientInterface $http,
        private Sleeper $sleeper,
    ) {
    }

    public function supermarketId(): SupermarketId
    {
        return SupermarketId::fromString('bonpreu');
    }

    public function fetchCatalog(PostalCode $postalCode): iterable
    {
        $headers = $this->openSession();
        $categories = $this->getJson(self::BASE_URL.'/api/webproductpagews/v1/categories?decoration=false&categoryDepth=4', $headers, pause: false);
        $returnedIds = [];

        foreach (array_filter($categories, \is_array(...)) as $category) {
            $categoryName = self::text($category, 'name');
            $pageToken = '';

            do {
                $page = $this->getJson(self::pageUrl(self::text($category, 'categoryId'), $pageToken), $headers);

                // Featured products and offers repeat products listed elsewhere: return each once.
                foreach (self::records($page, 'productGroups') as $group) {
                    foreach (self::records($group, 'decoratedProducts') as $product) {
                        $id = self::text($product, 'retailerProductId');
                        if ('' === $id || isset($returnedIds[$id])) {
                            continue;
                        }
                        $returnedIds[$id] = true;

                        yield self::map($product, $categoryName);
                    }
                }

                $metadata = \is_array($page['metadata'] ?? null) ? $page['metadata'] : [];
                $pageToken = self::text($metadata, 'nextPageToken');
            } while ('' !== $pageToken);
        }
    }

    /**
     * The headers every API call needs: the session cookies and the CSRF token of the home page.
     *
     * @return array<string, string>
     */
    private function openSession(): array
    {
        $response = $this->http->request('GET', self::BASE_URL.'/', [
            'headers' => ['User-Agent' => self::BROWSER_USER_AGENT, 'Accept' => 'text/html'],
        ]);

        if (1 !== preg_match(self::CSRF_PATTERN, $response->getContent(), $matches)) {
            throw new \RuntimeException('Bonpreu i Esclat did not return a CSRF token on its home page.');
        }

        $cookies = array_map(
            static fn (string $cookie): string => trim(explode(';', $cookie, 2)[0]),
            $response->getHeaders()['set-cookie'] ?? [],
        );

        return [
            'User-Agent' => self::BROWSER_USER_AGENT,
            'Accept' => 'application/json',
            'x-csrf-token' => $matches[1],
            'ecom-request-source' => 'web',
            'Cookie' => implode('; ', $cookies),
        ];
    }

    /**
     * The first page also asks for the page info, as the store does; the next ones follow the token.
     */
    private static function pageUrl(string $categoryId, string $pageToken): string
    {
        $paging = '' === $pageToken ? 'includeAdditionalPageInfo=true' : 'pageToken='.rawurlencode($pageToken);

        return \sprintf('%s/api/webproductpagews/v6/product-pages?%s&%s&categoryId=%s', self::BASE_URL, self::PAGE_QUERY, $paging, rawurlencode($categoryId));
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<mixed>
     */
    private function getJson(string $url, array $headers, bool $pause = true): array
    {
        if ($pause) {
            $this->sleeper->sleep(self::PAUSE_BETWEEN_API_REQUESTS_IN_MILLISECONDS);
        }

        for ($retry = 0;; ++$retry) {
            $response = $this->http->request('GET', $url, ['headers' => $headers]);
            if (!isset($response->getHeaders(false)['x-amzn-waf-action'])) {
                return $response->toArray();
            }
            if (self::RETRIES_AFTER_A_CHALLENGE === $retry) {
                throw new \RuntimeException(\sprintf('The Bonpreu i Esclat firewall challenged %s (HTTP %d) %d times in a row. Wait about five minutes before trying again.', $url, $response->getStatusCode(), $retry + 1));
            }
            $this->sleeper->sleep(self::PAUSE_AFTER_A_CHALLENGE_IN_MILLISECONDS);
        }
    }

    /**
     * @param array<mixed> $product
     */
    private static function map(array $product, string $categoryName): SourceProduct
    {
        $price = \is_array($product['price'] ?? null) ? $product['price'] : [];
        $image = \is_array($product['image'] ?? null) ? self::text($product['image'], 'src') : '';
        $brand = self::text($product, 'brand');
        $packaging = self::packaging(self::text($product, 'packSizeDescription'));
        $unitPrice = \is_array($product['unitPrice'] ?? null) ? $product['unitPrice'] : [];
        $referenceUnit = self::REFERENCE_UNITS[self::text($unitPrice, 'unit')] ?? null;
        // Sold by variable weight ("150g - 400g"), or by the piece with no size: the size is
        // approximate, so the product is priced by the store's own unit price (PD-29).
        $approximate = isset($product['catchweight']) || null === $packaging;
        $referencePrice = \is_array($unitPrice['price'] ?? null) ? self::text($unitPrice['price'], 'amount') : '';

        return new SourceProduct(
            externalId: self::text($product, 'retailerProductId'),
            name: trim(self::text($product, 'name')),
            priceCents: DecimalCents::fromString(self::text($price, 'amount')),
            packaging: $approximate ? null : $packaging,
            brand: '' === $brand ? null : $brand,
            categoryPath: self::categoryPath($product, $categoryName),
            imageUrl: false === filter_var($image, \FILTER_VALIDATE_URL) ? null : $image,
            referenceUnitPriceCents: $approximate && null !== $referenceUnit ? DecimalCents::fromString($referencePrice) : null,
            referenceUnit: $approximate ? $referenceUnit : null,
        );
    }

    /**
     * "12 per paquet" (a box of 12 eggs) becomes "12 ud"; sizes such as "6 x 1L" pass through.
     */
    private static function packaging(string $packSize): ?string
    {
        $packaging = trim((string) preg_replace('/ per paquet$/u', ' ud', trim($packSize)));

        return '' === $packaging ? null : $packaging;
    }

    /**
     * The product's own path, unless it points to a promotion ("Ofertes > Preu rebaixat") or
     * another top category: then the top category being read.
     *
     * @param array<mixed> $product
     *
     * @return list<string>
     */
    private static function categoryPath(array $product, string $categoryName): array
    {
        $path = \is_array($product['categoryPath'] ?? null) ? array_values(array_filter($product['categoryPath'], \is_string(...))) : [];

        return ($path[0] ?? null) === $categoryName ? $path : [$categoryName];
    }

    /**
     * The JSON objects in $data[$key], ignoring anything that is not one.
     *
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    private static function records(array $data, string $key): array
    {
        $items = $data[$key] ?? [];

        return \is_array($items) ? array_values(array_filter($items, \is_array(...))) : [];
    }

    /**
     * @param array<mixed> $data
     */
    private static function text(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return \is_string($value) || \is_int($value) ? (string) $value : '';
    }
}
