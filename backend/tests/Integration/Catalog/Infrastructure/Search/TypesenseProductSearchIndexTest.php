<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Search;

use App\Catalog\Infrastructure\Search\InvalidSynonymFile;
use PHPUnit\Framework\Attributes\Test;

final class TypesenseProductSearchIndexTest extends TypesenseTestCase
{
    #[Test]
    public function it_indexes_every_product_of_the_enabled_supermarkets(): void
    {
        $this->seed(1, 'Leche entera', supermarket: 'mercadona');
        $this->seed(2, 'Llet sencera', supermarket: 'aldi', available: false);
        $this->seed(3, 'Llet sencera', supermarket: 'bonpreu');

        $indexed = $this->index()->rebuild();

        self::assertSame(2, $indexed, 'bonpreu is not enabled in the tests');
        self::assertSame([self::id(1), self::id(2)], $this->indexedIds());
    }

    #[Test]
    public function it_points_the_alias_to_the_new_collection_and_loads_the_synonyms_into_it(): void
    {
        $this->seed(1, 'Leche entera');

        $this->index()->rebuild();

        $collection = $this->aliasedCollection();
        $schema = $this->typesense->request('GET', '/collections/'.$collection);
        self::assertSame(1, $schema['num_documents']);
        self::assertIsArray($schema['synonym_sets']);
        self::assertCount(1, $schema['synonym_sets']);
        $synonyms = $this->typesense->request('GET', '/synonym_sets/'.self::string($schema['synonym_sets'][0]));
        self::assertIsArray($synonyms['items']);
        self::assertSame(
            [['leche', 'llet'], ['huevo', 'huevos', 'ou', 'ous']],
            array_map(static fn (mixed $item): mixed => \is_array($item) ? $item['synonyms'] : null, $synonyms['items']),
        );
    }

    #[Test]
    public function a_second_rebuild_replaces_the_first_and_drops_its_collection(): void
    {
        $this->seed(1, 'Leche entera');
        $this->seed(2, 'Leche semi');
        $index = $this->index();
        $index->rebuild();
        $first = $this->aliasedCollection();

        $this->connection->executeStatement('DELETE FROM product WHERE name = ?', ['Leche semi']);
        $this->connection->executeStatement('UPDATE product SET price_cents = 150');
        $indexed = $index->rebuild();

        self::assertSame(1, $indexed);
        self::assertNotSame($first, $this->aliasedCollection());
        self::assertNull($this->typesense->requestOrNull('GET', '/collections/'.$first));
        self::assertSame([self::id(1)], $this->indexedIds());
        self::assertSame(150, $this->indexedDocuments()[0]['price_cents']);
        self::assertCount(1, $this->setsOfThisTest(), 'the synonyms of the dropped collection are dropped too');
    }

    #[Test]
    public function it_is_empty_without_an_index_or_without_products(): void
    {
        $index = $this->index();
        self::assertTrue($index->isEmpty());

        $index->rebuild();
        self::assertTrue($index->isEmpty());

        $this->seed(1, 'Leche entera');
        $index->rebuild();
        self::assertFalse($index->isEmpty());
    }

    #[Test]
    public function it_keeps_the_current_index_when_the_synonym_file_is_invalid(): void
    {
        $this->seed(1, 'Leche entera');
        $this->index()->rebuild();
        $current = $this->aliasedCollection();

        try {
            $this->index(__DIR__.'/../../../../Fixtures/Search/one-word.yaml')->rebuild();
            self::fail('An invalid synonym file must stop the rebuild.');
        } catch (InvalidSynonymFile) {
        }

        self::assertSame($current, $this->aliasedCollection());
        self::assertCount(1, $this->collectionsOfThisTest());
    }

    private function aliasedCollection(): string
    {
        return self::string($this->typesense->request('GET', '/aliases/'.self::ALIAS)['collection_name']);
    }

    /**
     * @return list<string>
     */
    private function indexedIds(): array
    {
        $ids = array_map(static fn (array $document): string => self::string($document['id']), $this->indexedDocuments());
        sort($ids);

        return $ids;
    }

    /**
     * @return list<array<mixed>>
     */
    private function indexedDocuments(): array
    {
        return $this->typesense->exportDocuments($this->aliasedCollection());
    }

    /**
     * @return list<string>
     */
    private function collectionsOfThisTest(): array
    {
        return self::namesStartingWithAlias($this->typesense->request('GET', '/collections'));
    }

    /**
     * @return list<string>
     */
    private function setsOfThisTest(): array
    {
        return self::namesStartingWithAlias($this->typesense->request('GET', '/synonym_sets'));
    }

    /**
     * @param array<mixed> $entries
     *
     * @return list<string>
     */
    private static function namesStartingWithAlias(array $entries): array
    {
        $names = [];
        foreach ($entries as $entry) {
            if (\is_array($entry) && \is_string($entry['name'] ?? null) && str_starts_with($entry['name'], self::ALIAS.'_')) {
                $names[] = $entry['name'];
            }
        }

        return $names;
    }

    private static function string(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
