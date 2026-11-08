<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Source\Mercadona;

use App\Catalog\Domain\Port\SourceProduct;
use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Source\DecimalCents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Mercadona through the internal JSON API of tienda.mercadona.es (PD-28, spike E1.13).
 *
 * The postal code is turned into a warehouse, then every leaf category is read with that
 * warehouse: about 150 requests per run.
 */
final readonly class MercadonaCatalogSource implements SupermarketCatalogSource
{
    private const string BASE_URL = 'https://tienda.mercadona.es/api';

    /** Mercadona's reference formats that map to our units. Others (such as "dc", a dozen) are ignored. */
    private const array REFERENCE_UNITS = ['kg' => 'kg', 'L' => 'l', 'l' => 'l', 'ud' => 'unit'];

    public function __construct(
        #[Autowire(service: 'catalog.http_client')]
        private HttpClientInterface $http,
    ) {
    }

    public function supermarketId(): SupermarketId
    {
        return SupermarketId::fromString('mercadona');
    }

    public function fetchCatalog(PostalCode $postalCode): iterable
    {
        $warehouse = $this->warehouseFor($postalCode);

        foreach (self::records($this->getJson(self::BASE_URL.'/categories/?lang=es'), 'results') as $topCategory) {
            foreach (self::records($topCategory, 'categories') as $leafCategory) {
                $url = \sprintf('%s/categories/%s/?lang=es&wh=%s', self::BASE_URL, rawurlencode(self::text($leafCategory, 'id')), rawurlencode($warehouse));
                foreach (self::records($this->getJson($url), 'categories') as $subCategory) {
                    $categoryPath = [self::text($topCategory, 'name'), self::text($leafCategory, 'name'), self::text($subCategory, 'name')];
                    foreach (self::records($subCategory, 'products') as $product) {
                        yield self::map($product, $categoryPath);
                    }
                }
            }
        }
    }

    private function warehouseFor(PostalCode $postalCode): string
    {
        $response = $this->http->request('PUT', self::BASE_URL.'/postal-codes/actions/change-pc/', [
            'json' => ['new_postal_code' => $postalCode->value()],
        ]);

        $warehouse = $response->getHeaders()['x-customer-wh'][0] ?? '';
        if ('' === $warehouse) {
            throw new \RuntimeException(\sprintf('Mercadona did not return a warehouse for postal code %s.', $postalCode->value()));
        }

        return $warehouse;
    }

    /**
     * @return array<mixed>
     */
    private function getJson(string $url): array
    {
        return $this->http->request('GET', $url)->toArray();
    }

    /**
     * @param array<mixed> $product
     * @param list<string> $categoryPath
     */
    private static function map(array $product, array $categoryPath): SourceProduct
    {
        $price = \is_array($product['price_instructions'] ?? null) ? $product['price_instructions'] : [];
        $approximate = true === ($price['approx_size'] ?? false);
        $referenceUnit = self::REFERENCE_UNITS[self::text($price, 'reference_format')] ?? null;
        $thumbnail = self::text($product, 'thumbnail');

        return new SourceProduct(
            externalId: self::text($product, 'id'),
            name: self::text($product, 'display_name'),
            priceCents: DecimalCents::fromString(self::text($price, 'unit_price')),
            packaging: $approximate ? null : self::packaging($price),
            brand: null,
            categoryPath: $categoryPath,
            imageUrl: '' === $thumbnail ? null : $thumbnail,
            // Only an approximate size is priced by Mercadona's own reference (PD-29).
            referenceUnitPriceCents: $approximate && null !== $referenceUnit ? DecimalCents::fromString(self::text($price, 'reference_price')) : null,
            referenceUnit: $approximate ? $referenceUnit : null,
        );
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

        return \is_string($value) || \is_int($value) || \is_float($value) ? (string) $value : '';
    }

    /**
     * "unit_size" is already the total size (a pack of 12 x 0.2 l is 2.4 l).
     *
     * @param array<mixed> $price
     */
    private static function packaging(array $price): ?string
    {
        $size = $price['unit_size'] ?? null;
        $format = $price['size_format'] ?? null;
        if (!is_numeric($size) || !\is_string($format)) {
            return null;
        }

        $quantity = rtrim(rtrim(number_format((float) $size, 3, '.', ''), '0'), '.');

        return $quantity.' '.$format;
    }
}
