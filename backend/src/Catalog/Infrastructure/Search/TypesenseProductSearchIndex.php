<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use App\Catalog\Application\ReindexProductSearch\ProductSearchIndex;
use App\Catalog\Application\SearchProducts\EnabledSupermarkets;
use App\Catalog\Application\SearchProducts\SearchUnavailable;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalColumn;
use App\Shared\Domain\IdGenerator;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Rebuilds the Typesense index from MySQL (PD-100): a new collection with its own synonym set (PD-101) is filled, the
 * alias is switched to it, and only then the previous collection is dropped, so search never sees a half-built index.
 *
 * Each document is a search result (TypesenseProductSearchReadModel) plus the fields that search matches, filters and
 * sorts on: `words` holds every prefix of every word of the name and brand (SearchWords), `unit_price_sort` is the
 * unit price or 0, and `order_key` breaks unit price ties by shelf price, then name, then id (PD-33): Typesense sorts
 * on three fields at most, so name and id become the product's rank in the MySQL order (same collation as before).
 */
final readonly class TypesenseProductSearchIndex implements ProductSearchIndex
{
    private const int BATCH_SIZE = 1000;

    /** Ranks of the name order stay below it, so price × RANKS + rank orders by price, then rank. */
    public const int RANKS = 10_000_000;

    public function __construct(
        private TypesenseClient $typesense,
        private Connection $connection,
        private EnabledSupermarkets $enabledSupermarkets,
        private IdGenerator $idGenerator,
        private string $synonymFile,
        private string $alias,
    ) {
    }

    public function rebuild(): int
    {
        $groups = new SynonymFile($this->synonymFile)->groups();
        $collection = $this->alias.'_'.str_replace('-', '', $this->idGenerator->generate()->value());
        $synonymSet = $collection.'_synonyms';

        try {
            $this->typesense->request('PUT', '/synonym_sets/'.$synonymSet, json: ['items' => self::synonymItems($groups)]);
            $this->typesense->request('POST', '/collections', json: self::schema($collection, $synonymSet));
            $indexed = $this->fill($collection);
        } catch (SearchUnavailable $failure) {
            $this->dropQuietly($collection, [$synonymSet]);

            throw $failure;
        }

        $previous = $this->aliasedCollection();
        $this->typesense->request('PUT', '/aliases/'.$this->alias, json: ['collection_name' => $collection]);
        if (null !== $previous) {
            $this->drop($previous);
        }

        return $indexed;
    }

    public function isEmpty(): bool
    {
        $collection = $this->aliasedCollection();
        if (null === $collection) {
            return true;
        }

        $schema = $this->typesense->requestOrNull('GET', '/collections/'.$collection);

        return null === $schema || 0 === ($schema['num_documents'] ?? 0);
    }

    private function aliasedCollection(): ?string
    {
        $alias = $this->typesense->requestOrNull('GET', '/aliases/'.$this->alias);
        $collection = $alias['collection_name'] ?? null;

        return \is_string($collection) ? $collection : null;
    }

    private function fill(string $collection): int
    {
        $supermarkets = array_map(static fn (SupermarketId $id): string => $id->value(), $this->enabledSupermarkets->enabledSupermarkets());
        $rows = $this->connection->iterateAssociative(
            'SELECT p.id, p.name, p.brand, p.supermarket_id, s.name AS supermarket_name, p.package_quantity, p.package_unit,
                    p.price_cents, p.unit_price_cents, p.unit_price_unit, p.image_url, p.available
             FROM product p JOIN supermarket s ON s.id = p.supermarket_id
             WHERE p.supermarket_id IN (:supermarkets)
             ORDER BY p.name, p.id',
            ['supermarkets' => $supermarkets],
            ['supermarkets' => ArrayParameterType::STRING],
        );

        $rank = 0;
        $batch = [];
        foreach ($rows as $row) {
            $batch[] = self::document($row, $rank++);
            if (\count($batch) >= self::BATCH_SIZE) {
                $this->typesense->importDocuments($collection, $batch);
                $batch = [];
            }
        }
        $this->typesense->importDocuments($collection, $batch);

        return $rank;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function document(array $row, int $rank): array
    {
        if ($rank >= self::RANKS) {
            throw SearchUnavailable::because(\sprintf('more than %d products cannot be ordered by name.', self::RANKS));
        }

        $name = DbalColumn::string($row['name']);
        $brand = DbalColumn::nullableString($row['brand']);
        $priceCents = DbalColumn::int($row['price_cents']);
        $unitPriceCents = DbalColumn::nullableInt($row['unit_price_cents']);

        return array_filter([
            'id' => BinaryUuid::fromDatabase(DbalColumn::string($row['id'])),
            'name' => $name,
            'brand' => $brand,
            'supermarket_id' => DbalColumn::string($row['supermarket_id']),
            'supermarket_name' => DbalColumn::string($row['supermarket_name']),
            'package_quantity' => DbalColumn::nullableInt($row['package_quantity']),
            'package_unit' => DbalColumn::nullableString($row['package_unit']),
            'price_cents' => $priceCents,
            'unit_price_cents' => $unitPriceCents,
            'unit_price_unit' => DbalColumn::nullableString($row['unit_price_unit']),
            'image_url' => DbalColumn::nullableString($row['image_url']),
            'available' => 1 === DbalColumn::int($row['available']),
            'words' => SearchWords::prefixesOf($name.' '.($brand ?? '')),
            'unit_price_sort' => $unitPriceCents ?? 0,
            'order_key' => $priceCents * self::RANKS + $rank,
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * Only the fields search matches, filters, facets or sorts on are declared; the others are stored as they come.
     *
     * @return array<string, mixed>
     */
    private static function schema(string $collection, string $synonymSet): array
    {
        return [
            'name' => $collection,
            'fields' => [
                ['name' => 'words', 'type' => 'string[]'],
                ['name' => 'supermarket_id', 'type' => 'string'],
                ['name' => 'available', 'type' => 'bool'],
                ['name' => 'unit_price_unit', 'type' => 'string', 'facet' => true, 'optional' => true],
                ['name' => 'unit_price_sort', 'type' => 'int64'],
                ['name' => 'order_key', 'type' => 'int64'],
            ],
            'synonym_sets' => [$synonymSet],
        ];
    }

    /**
     * @param list<non-empty-list<string>> $groups
     *
     * @return list<array{id: string, synonyms: list<string>}>
     */
    private static function synonymItems(array $groups): array
    {
        $items = [];
        foreach ($groups as $index => $group) {
            $items[] = [
                'id' => 'group-'.($index + 1),
                'synonyms' => array_map(static fn (string $word): string => SearchWords::of($word)[0], $group),
            ];
        }

        return $items;
    }

    private function drop(string $collection): void
    {
        $schema = $this->typesense->requestOrNull('GET', '/collections/'.$collection);
        $sets = array_values(array_filter(\is_array($schema['synonym_sets'] ?? null) ? $schema['synonym_sets'] : [], \is_string(...)));
        $this->typesense->requestOrNull('DELETE', '/collections/'.$collection);
        foreach ($sets as $set) {
            $this->typesense->requestOrNull('DELETE', '/synonym_sets/'.$set);
        }
    }

    /**
     * @param list<string> $synonymSets
     */
    private function dropQuietly(string $collection, array $synonymSets): void
    {
        try {
            $this->typesense->requestOrNull('DELETE', '/collections/'.$collection);
            foreach ($synonymSets as $set) {
                $this->typesense->requestOrNull('DELETE', '/synonym_sets/'.$set);
            }
        } catch (SearchUnavailable) {
            // The rebuild already failed; that error is the one to report.
        }
    }
}
