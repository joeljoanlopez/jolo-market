<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\GetProduct\ProductView;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalProductViewReadModel;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;

final class DbalProductViewReadModelTest extends DbalTestCase
{
    private const string ID = '00000000-0000-4000-8000-000000000001';

    #[Test]
    public function it_maps_every_field_of_a_product(): void
    {
        $this->insert([
            'supermarket_id' => 'bonpreu', 'name' => 'Leche entera', 'brand' => 'Hacendado', 'image_url' => 'https://example.com/leche.jpg',
            'package_quantity' => 1500, 'package_unit' => 'l', 'price_cents' => 125, 'unit_price_cents' => 83, 'unit_price_unit' => 'l', 'available' => true,
        ]);

        self::assertEquals(
            new ProductView(self::ID, 'Leche entera', 'Hacendado', 'bonpreu', 'Bonpreu i Esclat', 1500, 'l', 125, 83, 'l', 'https://example.com/leche.jpg', true),
            $this->readModel()->find(ProductId::fromString(self::ID)),
        );
    }

    #[Test]
    public function it_maps_an_unavailable_product_without_optional_data(): void
    {
        $this->insert(['supermarket_id' => 'mercadona', 'name' => 'Leche', 'price_cents' => 100, 'available' => false]);

        self::assertEquals(
            new ProductView(self::ID, 'Leche', null, 'mercadona', 'Mercadona', null, null, 100, null, null, null, false),
            $this->readModel()->find(ProductId::fromString(self::ID)),
        );
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->readModel()->find(ProductId::fromString(self::ID)));
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function insert(array $columns): void
    {
        $this->connection->insert(
            'product',
            ['id' => BinaryUuid::toDatabase(self::ID), 'external_id' => '1', 'last_seen_at' => '2026-11-08 06:00:00', ...$columns],
            ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN],
        );
    }

    private function readModel(): DbalProductViewReadModel
    {
        return new DbalProductViewReadModel($this->connection);
    }
}
