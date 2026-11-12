<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\GetPriceHistory\PriceHistoryPoint;
use App\Catalog\Application\GetPriceHistory\PriceHistoryReadModel;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Reads through INDEX(product_id, captured_at), so a range is an index range scan.
 */
final readonly class DbalPriceHistoryReadModel implements PriceHistoryReadModel
{
    public function __construct(private Connection $connection)
    {
    }

    public function pointsOf(ProductId $id, ?\DateTimeImmutable $from, ?\DateTimeImmutable $before): array
    {
        $sql = 'SELECT price_cents, unit_price_cents, unit_price_unit, captured_at FROM price_snapshot WHERE product_id = :id';
        $params = ['id' => BinaryUuid::toDatabase($id->value())];
        if (null !== $from) {
            $sql .= ' AND captured_at >= :from';
            $params['from'] = DbalDateTime::toDatabase($from);
        }
        if (null !== $before) {
            $sql .= ' AND captured_at < :before';
            $params['before'] = DbalDateTime::toDatabase($before);
        }

        $rows = $this->connection->fetchAllAssociative($sql.' ORDER BY captured_at, id', $params, ['id' => ParameterType::BINARY]);

        return array_map(
            static fn (array $row): PriceHistoryPoint => new PriceHistoryPoint(
                DbalDateTime::fromDatabase(DbalColumn::string($row['captured_at'])),
                DbalColumn::int($row['price_cents']),
                DbalColumn::nullableInt($row['unit_price_cents']),
                DbalColumn::nullableString($row['unit_price_unit']),
            ),
            $rows,
        );
    }
}
