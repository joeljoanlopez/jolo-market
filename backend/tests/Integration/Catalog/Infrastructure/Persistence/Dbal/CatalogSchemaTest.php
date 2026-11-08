<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use PHPUnit\Framework\Attributes\Test;

final class CatalogSchemaTest extends DbalTestCase
{
    #[Test]
    public function it_seeds_the_three_mvp_supermarkets(): void
    {
        $rows = $this->connection->fetchAllKeyValue('SELECT id, name FROM supermarket ORDER BY id');

        self::assertSame(['aldi' => 'Aldi', 'bonpreu' => 'Bonpreu i Esclat', 'mercadona' => 'Mercadona'], $rows);
    }

    #[Test]
    public function it_keeps_one_product_per_supermarket_and_external_id(): void
    {
        $insert = "INSERT INTO product (id, supermarket_id, external_id, name, price_cents, available, last_seen_at)
                   VALUES (?, 'mercadona', '1', 'Leche', 99, 1, '2026-11-08 06:00:00')";
        $this->connection->executeStatement($insert, [random_bytes(16)]);

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->connection->executeStatement($insert, [random_bytes(16)]);
    }
}
