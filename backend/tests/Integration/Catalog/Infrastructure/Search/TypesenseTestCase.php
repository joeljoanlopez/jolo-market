<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Search;

use App\Catalog\Application\SearchProducts\EnabledSupermarkets;
use App\Catalog\Infrastructure\Search\TypesenseClient;
use App\Catalog\Infrastructure\Search\TypesenseProductSearchIndex;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal\DbalTestCase;
use App\Tests\Support\SequentialIdGenerator;
use Doctrine\DBAL\ParameterType;

/**
 * Runs against the Typesense service of the Compose stack, on collections of its own (the "products_it" alias), so it
 * never touches the index of development or of the functional tests.
 */
abstract class TypesenseTestCase extends DbalTestCase
{
    protected const string ALIAS = 'products_it';

    protected TypesenseClient $typesense;

    private int $nextExternalId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $typesense = self::getContainer()->get('test.catalog.typesense_client');
        self::assertInstanceOf(TypesenseClient::class, $typesense);
        $this->typesense = $typesense;

        $this->typesense->requestOrNull('DELETE', '/aliases/'.self::ALIAS);
        foreach ($this->typesense->request('GET', '/collections') as $collection) {
            if (\is_array($collection) && \is_string($collection['name'] ?? null) && str_starts_with($collection['name'], self::ALIAS.'_')) {
                $this->typesense->request('DELETE', '/collections/'.$collection['name']);
            }
        }
        foreach ($this->typesense->request('GET', '/synonym_sets') as $set) {
            if (\is_array($set) && \is_string($set['name'] ?? null) && str_starts_with($set['name'], self::ALIAS.'_')) {
                $this->typesense->request('DELETE', '/synonym_sets/'.$set['name']);
            }
        }
    }

    protected function index(string $synonymFile = __DIR__.'/../../../../Fixtures/Search/valid.yaml'): TypesenseProductSearchIndex
    {
        $enabled = self::getContainer()->get(EnabledSupermarkets::class);
        self::assertInstanceOf(EnabledSupermarkets::class, $enabled);

        return new TypesenseProductSearchIndex($this->typesense, $this->connection, $enabled, new SequentialIdGenerator(), $synonymFile, self::ALIAS);
    }

    protected function seed(
        int $number,
        string $name,
        ?string $brand = null,
        string $supermarket = 'mercadona',
        int $priceCents = 100,
        ?int $unitPriceCents = 100,
        ?string $unit = 'l',
        ?int $packageQuantity = 1000,
        ?string $packageUnit = 'l',
        ?string $imageUrl = null,
        bool $available = true,
    ): void {
        $this->connection->insert('product', [
            'id' => BinaryUuid::toDatabase(self::id($number)),
            'supermarket_id' => $supermarket,
            'external_id' => (string) $this->nextExternalId++,
            'name' => $name,
            'brand' => $brand,
            'image_url' => $imageUrl,
            'package_quantity' => $packageQuantity,
            'package_unit' => $packageUnit,
            'price_cents' => $priceCents,
            'unit_price_cents' => $unitPriceCents,
            'unit_price_unit' => $unit,
            'available' => $available,
            'last_seen_at' => '2026-11-08 06:00:00',
        ], ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN]);
    }

    protected static function id(int $number): string
    {
        return \sprintf('00000000-0000-4000-8000-%012d', $number);
    }
}
