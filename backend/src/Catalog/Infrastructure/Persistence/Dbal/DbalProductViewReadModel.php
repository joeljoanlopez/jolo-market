<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\GetProduct\ProductView;
use App\Catalog\Application\GetProduct\ProductViewReadModel;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalProductViewReadModel implements ProductViewReadModel
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(ProductId $id): ?ProductView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT p.name, p.brand, p.supermarket_id, s.name AS supermarket_name, p.package_quantity, p.package_unit,
                    p.price_cents, p.unit_price_cents, p.unit_price_unit, p.image_url, p.available
             FROM product p JOIN supermarket s ON s.id = p.supermarket_id
             WHERE p.id = :id',
            ['id' => BinaryUuid::toDatabase($id->value())],
            ['id' => ParameterType::BINARY],
        );

        if (false === $row) {
            return null;
        }

        return new ProductView(
            $id->value(),
            DbalColumn::string($row['name']),
            DbalColumn::nullableString($row['brand']),
            DbalColumn::string($row['supermarket_id']),
            DbalColumn::string($row['supermarket_name']),
            DbalColumn::nullableInt($row['package_quantity']),
            DbalColumn::nullableString($row['package_unit']),
            DbalColumn::int($row['price_cents']),
            DbalColumn::nullableInt($row['unit_price_cents']),
            DbalColumn::nullableString($row['unit_price_unit']),
            DbalColumn::nullableString($row['image_url']),
            1 === DbalColumn::int($row['available']),
        );
    }
}
