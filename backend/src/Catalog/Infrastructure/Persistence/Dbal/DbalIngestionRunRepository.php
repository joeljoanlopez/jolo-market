<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\IngestCatalog\IngestionRun;
use App\Catalog\Application\IngestCatalog\IngestionRunRepository;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Shared\Infrastructure\Persistence\Dbal\DbalDateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalIngestionRunRepository implements IngestionRunRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function save(IngestionRun $run): void
    {
        $finishedAt = $run->finishedAt();

        $this->connection->executeStatement(
            'INSERT INTO ingestion_run (id, supermarket_id, started_at, finished_at, status,
                                        products_seen, products_skipped, products_marked_unavailable, error)
             VALUES (:id, :supermarket_id, :started_at, :finished_at, :status,
                     :products_seen, :products_skipped, :products_marked_unavailable, :error)
             AS new
             ON DUPLICATE KEY UPDATE
                finished_at = new.finished_at, status = new.status,
                products_seen = new.products_seen, products_skipped = new.products_skipped,
                products_marked_unavailable = new.products_marked_unavailable, error = new.error',
            [
                'id' => BinaryUuid::toDatabase($run->id()->value()),
                'supermarket_id' => $run->supermarketId()->value(),
                'started_at' => DbalDateTime::toDatabase($run->startedAt()),
                'finished_at' => null === $finishedAt ? null : DbalDateTime::toDatabase($finishedAt),
                'status' => $run->status()->value,
                'products_seen' => $run->productsSeen(),
                'products_skipped' => $run->productsSkipped(),
                'products_marked_unavailable' => $run->productsMarkedUnavailable(),
                'error' => $run->error(),
            ],
            ['id' => ParameterType::BINARY],
        );
    }
}
