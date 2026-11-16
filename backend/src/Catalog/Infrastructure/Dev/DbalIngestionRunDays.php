<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Dev;

use App\Catalog\Infrastructure\Persistence\Dbal\DbalColumn;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\Connection;

/**
 * The days that already have an ingestion run, so the backfill can skip them (PD-55).
 */
final readonly class DbalIngestionRunDays
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array<string, list<string>> by supermarket slug, the UTC days (Y-m-d) since $from with at least one run
     */
    public function since(\DateTimeImmutable $from): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT supermarket_id, DATE(started_at) AS day FROM ingestion_run WHERE started_at >= ?',
            [DbalDateTime::toDatabase($from)],
        );

        $days = [];
        foreach ($rows as $row) {
            $days[DbalColumn::string($row['supermarket_id'])][] = DbalColumn::string($row['day']);
        }

        return $days;
    }
}
