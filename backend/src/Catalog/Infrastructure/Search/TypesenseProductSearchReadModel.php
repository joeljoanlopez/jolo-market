<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use App\Catalog\Application\SearchProducts\ProductSearchPage;
use App\Catalog\Application\SearchProducts\ProductSearchReadModel;
use App\Catalog\Application\SearchProducts\ProductSearchResult;
use App\Catalog\Application\SearchProducts\SearchUnavailable;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Search on the Typesense index of TypesenseProductSearchIndex (PD-100).
 *
 * Matching: each typed word (SearchWords) must equal one of the indexed prefixes of the name and brand, so every word
 * matches the start of a word (PD-33). Typesense applies the dictionary to whole typed words only, and the equivalent
 * is then also matched against the prefixes, so "pa" finds pan* and "lec" gets no equivalent (PD-101). No typos, no
 * dropped words.
 *
 * Only when that finds nothing, the same query runs again with typos and its page is marked approximate (PD-103).
 *
 * Order (PD-33): Typesense sorts on three fields at most. The first one ranks the unit groups of this query (more
 * results first, ties kg, l, unit, products without a unit price last), computed from a facet of the same query; then
 * unit price; then `order_key` (shelf price, then name and id).
 */
final readonly class TypesenseProductSearchReadModel implements ProductSearchReadModel
{
    /**
     * The fallback when nothing matches exactly (PD-103), within the same filter (PD-124): no typo under 4 letters,
     * 1 from 4 to 6 letters, 2 from 7 (PD-125). Every candidate correction is tried, not only the first few, and no
     * word is dropped.
     */
    private const array TYPOS = [
        'num_typos' => 2,
        'min_len_1typo' => 4,
        'min_len_2typo' => 7,
        'typo_tokens_threshold' => 1000,
        'max_candidates' => 100,
    ];

    /** The order of units with as many results (PD-33). */
    private const array UNITS = ['kg', 'l', 'unit'];

    public function __construct(private TypesenseClient $typesense, private string $alias)
    {
    }

    public function search(string $text, array $supermarkets, int $page, int $perPage): ProductSearchPage
    {
        $words = SearchWords::of($text);
        if ([] === $words || [] === $supermarkets) {
            return new ProductSearchPage([], 0, $page, $perPage, []);
        }

        $exact = [
            'q' => implode(' ', $words),
            'query_by' => 'words',
            'filter_by' => self::filter($supermarkets),
            'prefix' => false,
            'num_typos' => 0,
            'drop_tokens_threshold' => 0,
            'typo_tokens_threshold' => 0,
        ];

        return $this->page($exact, $page, $perPage, approximate: false)
            ?? $this->page([...$exact, ...self::TYPOS], $page, $perPage, approximate: true)
            ?? new ProductSearchPage([], 0, $page, $perPage, []);
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return ProductSearchPage|null null when the query has no result at all
     */
    private function page(array $query, int $page, int $perPage, bool $approximate): ?ProductSearchPage
    {
        [$total, $groupSizes, $cheapest] = $this->groups($query);
        if (0 === $total) {
            return null;
        }

        $hits = $this->searchOne(
            [...$query, 'sort_by' => self::order($groupSizes), 'page' => $page, 'per_page' => $perPage],
        )['hits'] ?? [];

        return new ProductSearchPage(
            array_map(self::result(...), \is_array($hits) ? array_values($hits) : []),
            $total,
            $page,
            $perPage,
            $cheapest,
            $approximate,
        );
    }

    /**
     * The results of the whole query: how many, how many per unit, and the cheapest unit price of each unit.
     *
     * @param array<string, scalar> $query
     *
     * @return array{int, array<string, int>, array<string, int>}
     */
    private function groups(array $query): array
    {
        $searches = [[...$query, 'per_page' => 0, 'facet_by' => 'unit_price_unit']];
        foreach (self::UNITS as $unit) {
            $searches[] = [
                ...$query,
                'filter_by' => $query['filter_by'].' && unit_price_unit:=`'.$unit.'`',
                'sort_by' => 'unit_price_sort:asc',
                'per_page' => 1,
                'include_fields' => 'unit_price_cents',
            ];
        }

        $results = $this->multiSearch($searches);
        $total = self::int($results[0]['found'] ?? 0);

        $groupSizes = [];
        $facets = \is_array($results[0]['facet_counts'] ?? null) ? $results[0]['facet_counts'] : [];
        foreach ($facets as $facet) {
            foreach (\is_array($facet) && \is_array($facet['counts'] ?? null) ? $facet['counts'] : [] as $count) {
                if (\is_array($count) && \is_string($count['value'] ?? null)) {
                    $groupSizes[$count['value']] = self::int($count['count'] ?? 0);
                }
            }
        }

        $cheapest = [];
        foreach (self::UNITS as $index => $unit) {
            $hits = $results[$index + 1]['hits'] ?? null;
            $document = \is_array($hits) && \is_array($hits[0] ?? null) && \is_array($hits[0]['document'] ?? null) ? $hits[0]['document'] : null;
            if (null !== $document && isset($document['unit_price_cents'])) {
                $cheapest[$unit] = self::int($document['unit_price_cents']);
            }
        }

        return [$total, $groupSizes, $cheapest];
    }

    /**
     * @param array<string, int> $groupSizes results per unit of this query
     */
    private static function order(array $groupSizes): string
    {
        $units = array_values(array_filter(self::UNITS, static fn (string $unit): bool => ($groupSizes[$unit] ?? 0) > 0));
        usort($units, static fn (string $a, string $b): int => [$groupSizes[$b], array_search($a, self::UNITS, true)]
            <=> [$groupSizes[$a], array_search($b, self::UNITS, true)]);

        $weights = [];
        foreach ($units as $position => $unit) {
            $weights[] = \sprintf('(unit_price_unit:=`%s`):%d', $unit, \count($units) - $position);
        }

        // A product without a unit price matches no expression, so it gets 0 and goes last.
        $groupRank = [] === $weights ? '' : '_eval(['.implode(', ', $weights).']):desc, ';

        return $groupRank.'unit_price_sort:asc, order_key:asc';
    }

    /**
     * @param list<SupermarketId> $supermarkets
     */
    private static function filter(array $supermarkets): string
    {
        $ids = array_map(static fn (SupermarketId $id): string => '`'.$id->value().'`', $supermarkets);

        return 'available:true && supermarket_id:['.implode(', ', $ids).']';
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<mixed>
     */
    private function searchOne(array $query): array
    {
        return $this->multiSearch([$query])[0];
    }

    /**
     * One request for several searches. A search that fails inside it is a failure too.
     *
     * @param list<array<string, scalar>> $searches
     *
     * @return list<array<mixed>>
     */
    private function multiSearch(array $searches): array
    {
        $response = $this->typesense->request('POST', '/multi_search', json: [
            'searches' => array_map(fn (array $search): array => ['collection' => $this->alias, ...$search], $searches),
        ]);

        $results = [];
        foreach (\is_array($response['results'] ?? null) ? $response['results'] : [] as $result) {
            if (!\is_array($result) || isset($result['error'])) {
                throw SearchUnavailable::because('a search failed: '.json_encode($result));
            }
            $results[] = $result;
        }

        if (\count($results) !== \count($searches)) {
            throw SearchUnavailable::because('the multi search answered fewer results than searches.');
        }

        return $results;
    }

    private static function result(mixed $hit): ProductSearchResult
    {
        $document = \is_array($hit) && \is_array($hit['document'] ?? null) ? $hit['document'] : [];

        return new ProductSearchResult(
            self::string($document['id'] ?? null),
            self::string($document['name'] ?? null),
            self::nullableString($document['brand'] ?? null),
            self::string($document['supermarket_id'] ?? null),
            self::string($document['supermarket_name'] ?? null),
            self::nullableInt($document['package_quantity'] ?? null),
            self::nullableString($document['package_unit'] ?? null),
            self::int($document['price_cents'] ?? null),
            self::nullableInt($document['unit_price_cents'] ?? null),
            self::nullableString($document['unit_price_unit'] ?? null),
            self::nullableString($document['image_url'] ?? null),
        );
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw SearchUnavailable::because('a document misses a text field.');
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    private static function int(mixed $value): int
    {
        return \is_int($value) ? $value : throw SearchUnavailable::because('a document misses a number field.');
    }

    private static function nullableInt(mixed $value): ?int
    {
        return null === $value ? null : self::int($value);
    }
}
