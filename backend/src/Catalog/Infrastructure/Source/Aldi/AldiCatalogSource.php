<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Source\Aldi;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Source\DecimalCents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Aldi through the public category pages of www.aldi.es (PD-31).
 *
 * Every page embeds its data as JSON in __NEXT_DATA__: the sub-categories of a section, and
 * the products of a leaf category with their price. The tree is walked from /productos, about
 * 165 requests per run. Prices are the peninsula ones, so the postal code is not used.
 */
final readonly class AldiCatalogSource implements SupermarketCatalogSource
{
    private const string BASE_URL = 'https://www.aldi.es';

    private const string ROOT_PATH = '/productos';

    private const string NEXT_DATA_PATTERN = '#<script id="__NEXT_DATA__" type="application/json">(.*?)</script>#s';

    private const string CHILDREN_REQUEST = 'PRODUCT_MGNL_CATEGORY_CHILDREN_GET';

    /** Aldi's reference price scales that map to our units. */
    private const array REFERENCE_UNITS = ['kg' => 'kg', 'l' => 'l', 'L' => 'l'];

    public function __construct(
        #[Autowire(service: 'catalog.http_client')]
        private HttpClientInterface $http,
    ) {
    }

    public function supermarketId(): SupermarketId
    {
        return SupermarketId::fromString('aldi');
    }

    public function fetchCatalog(PostalCode $postalCode): iterable
    {
        $queue = [self::ROOT_PATH];
        $visitedPaths = [];
        $returnedIds = [];

        while (null !== $path = array_shift($queue)) {
            if (isset($visitedPaths[$path])) {
                continue;
            }
            $visitedPaths[$path] = true;

            $page = $this->pageProps($path);
            array_push($queue, ...self::childPaths($page));

            // A product shows up in its own category and in its brand's: return it once.
            foreach (self::hits($page) as $hit) {
                $id = self::text($hit, 'objectID');
                if ('' === $id || isset($returnedIds[$id])) {
                    continue;
                }
                $returnedIds[$id] = true;

                yield self::map($hit);
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function pageProps(string $path): array
    {
        $url = self::BASE_URL.$path.'.html';
        $html = $this->http->request('GET', $url)->getContent();

        $data = 1 === preg_match(self::NEXT_DATA_PATTERN, $html, $matches) ? json_decode($matches[1], true) : null;
        $pageProps = \is_array($data) && \is_array($data['props'] ?? null) ? ($data['props']['pageProps'] ?? null) : null;
        if (!\is_array($pageProps)) {
            throw new \RuntimeException(\sprintf('The Aldi page %s has no page data.', $url));
        }

        return $pageProps;
    }

    /**
     * The sub-category paths of a section page. Leaf pages have none.
     *
     * @param array<mixed> $page
     *
     * @return list<string>
     */
    private static function childPaths(array $page): array
    {
        $requests = \is_string($page['apiData'] ?? null) ? json_decode($page['apiData'], true) : null;

        $paths = [];
        foreach (\is_array($requests) ? $requests : [] as $request) {
            if (!\is_array($request) || self::CHILDREN_REQUEST !== ($request[0] ?? null) || !\is_array($request[1] ?? null)) {
                continue;
            }
            foreach (self::records($request[1], 'res') as $child) {
                $path = self::text(\is_array($child['reference'] ?? null) ? $child['reference'] : [], 'path');
                if (str_starts_with($path, self::ROOT_PATH.'/')) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * The products a leaf page embeds, from its Algolia search results.
     *
     * @param array<mixed> $page
     *
     * @return list<array<mixed>>
     */
    private static function hits(array $page): array
    {
        $state = \is_array($page['algoliaState'] ?? null) ? $page['algoliaState'] : [];
        $indices = \is_array($state['initialResults'] ?? null) ? $state['initialResults'] : [];

        $hits = [];
        foreach (array_filter($indices, \is_array(...)) as $index) {
            foreach (self::records($index, 'results') as $result) {
                array_push($hits, ...self::records($result, 'hits'));
            }
        }

        return $hits;
    }

    /**
     * @param array<mixed> $hit
     */
    private static function map(array $hit): SourceProduct
    {
        $price = \is_array($hit['currentPrice'] ?? null) ? $hit['currentPrice'] : [];
        $salesUnit = mb_strtolower(self::text($hit, 'salesUnit'));
        $approximate = str_contains($salesUnit, 'aprox') || str_contains($salesUnit, 'granel');
        $reference = self::records($price, 'basePrice')[0] ?? [];
        $referenceUnit = self::REFERENCE_UNITS[self::text($reference, 'basePriceScale')] ?? null;
        $brand = trim(str_replace(['®', '™'], '', self::text($hit, 'brandName')));

        return new SourceProduct(
            externalId: self::text($hit, 'objectID'),
            name: self::text($hit, 'name'),
            priceCents: self::cents($price, 'priceValue'),
            packaging: $approximate ? null : self::packaging($salesUnit),
            brand: '' === $brand ? null : $brand,
            categoryPath: self::categoryPath($hit),
            imageUrl: self::imageUrl($hit),
            // Only an approximate size is priced by Aldi's own reference (PD-29).
            referenceUnitPriceCents: $approximate && null !== $referenceUnit ? self::cents($reference, 'basePriceValue') : null,
            referenceUnit: $approximate ? $referenceUnit : null,
        );
    }

    /**
     * "1 l unidad" becomes "1 l". Canned food only gives its drained weight ("63 g peso esc.
     * unidad"), which is the size it is compared by.
     */
    private static function packaging(string $salesUnit): ?string
    {
        $packaging = (string) preg_replace('/\s+/u', ' ', trim($salesUnit));
        $packaging = str_replace(' peso esc.', '', $packaging);
        $packaging = (string) preg_replace('/ unidad$/u', '', $packaging);

        return '' === $packaging ? null : $packaging;
    }

    /**
     * The product's own category ("Despensa > Conservas de pescado"), not the page it was
     * found on: a brand page lists products from many categories.
     *
     * @param array<mixed> $hit
     *
     * @return list<string>
     */
    private static function categoryPath(array $hit): array
    {
        $categories = \is_array($hit['hierarchicalCategories'] ?? null) ? $hit['hierarchicalCategories'] : [];
        $levels = \is_array($categories['lvl1'] ?? null) ? $categories['lvl1'] : [];
        $first = $levels[0] ?? null;
        if (!\is_string($first)) {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode('>', $first)), static fn (string $name): bool => '' !== $name));
    }

    /**
     * A broken image URL (Aldi has some with a trailing " ID") is left out, so it does not
     * make ingestion skip an otherwise valid product.
     *
     * @param array<mixed> $hit
     */
    private static function imageUrl(array $hit): ?string
    {
        foreach (self::records($hit, 'assets') as $asset) {
            if ('primary' === self::text($asset, 'type')) {
                $url = self::text($asset, 'url');

                return false === filter_var($url, \FILTER_VALIDATE_URL) ? null : $url;
            }
        }

        return null;
    }

    /**
     * Aldi gives prices as JSON numbers (0.96). Three decimals keep the half-up rounding exact.
     *
     * @param array<mixed> $data
     */
    private static function cents(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) || \is_float($value) ? DecimalCents::fromString(number_format($value, 3, '.', '')) : null;
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
