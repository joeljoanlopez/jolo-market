<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Domain\Model\PriceSnapshot;
use App\Catalog\Domain\Port\PriceSnapshotRepository;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalPriceSnapshotRepository implements PriceSnapshotRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * One multi-row INSERT per call. The ingestion handler sends batches of 500.
     */
    public function append(PriceSnapshot ...$snapshots): void
    {
        if ([] === $snapshots) {
            return;
        }

        $placeholders = [];
        $params = [];
        $types = [];
        foreach (array_values($snapshots) as $snapshot) {
            $placeholders[] = '(?, ?, ?, ?, ?)';
            $params[] = BinaryUuid::toDatabase($snapshot->productId()->value());
            $types[] = ParameterType::BINARY;
            array_push(
                $params,
                $snapshot->price()->cents(),
                $snapshot->unitPrice()?->price()->cents(),
                $snapshot->unitPrice()?->unit()->value,
                DbalDateTime::toDatabase($snapshot->capturedAt()),
            );
            array_push($types, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING);
        }

        $this->connection->executeStatement(
            'INSERT INTO price_snapshot (product_id, price_cents, unit_price_cents, unit_price_unit, captured_at) VALUES '
            .implode(', ', $placeholders),
            $params,
            $types,
        );
    }

    /**
     * MySQL cannot select from the table a DELETE targets, but it can join a derived table, which it materializes first.
     * Rows at the latest instant of their product are never deleted, however old.
     */
    public function deleteCapturedBefore(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE s FROM price_snapshot s
             JOIN (SELECT product_id, MAX(captured_at) AS latest FROM price_snapshot GROUP BY product_id) l
               ON l.product_id = s.product_id
             WHERE s.captured_at < :cutoff AND s.captured_at < l.latest',
            ['cutoff' => DbalDateTime::toDatabase($cutoff)],
        );
    }
}
