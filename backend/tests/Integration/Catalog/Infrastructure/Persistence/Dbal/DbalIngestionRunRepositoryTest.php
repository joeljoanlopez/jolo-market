<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Application\IngestCatalog\IngestionRun;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalIngestionRunRepository;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\Test;

final class DbalIngestionRunRepositoryTest extends DbalTestCase
{
    private const string RUN_ID = '00000000-0000-4000-8000-000000000001';

    #[Test]
    public function it_saves_a_running_run(): void
    {
        new DbalIngestionRunRepository($this->connection)->save(self::startedRun());

        self::assertSame([
            'id' => '00000000000040008000000000000001',
            'supermarket_id' => 'aldi',
            'started_at' => '2026-11-08 06:00:00.000000',
            'finished_at' => null,
            'status' => 'running',
            'products_seen' => 0,
            'products_skipped' => 0,
            'products_marked_unavailable' => 0,
            'error' => null,
        ], $this->storedRun());
    }

    #[Test]
    public function it_updates_a_run_when_it_finishes(): void
    {
        $repository = new DbalIngestionRunRepository($this->connection);
        $run = self::startedRun();
        $repository->save($run);

        $run->fail(new \DateTimeImmutable('2026-11-08 06:05:00', new \DateTimeZone('UTC')), 120, 3, 'HTTP 503 from the store.');
        $repository->save($run);

        $stored = $this->storedRun();
        self::assertSame('failed', $stored['status']);
        self::assertSame('2026-11-08 06:05:00.000000', $stored['finished_at']);
        self::assertSame(120, $stored['products_seen']);
        self::assertSame(3, $stored['products_skipped']);
        self::assertSame('HTTP 503 from the store.', $stored['error']);
        self::assertSame(1, $this->countRows('ingestion_run'));
    }

    private static function startedRun(): IngestionRun
    {
        return IngestionRun::start(
            Uuid::fromString(self::RUN_ID),
            SupermarketId::fromString('aldi'),
            new \DateTimeImmutable('2026-11-08 06:00:00', new \DateTimeZone('UTC')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRun(): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) AS id, supermarket_id, started_at, finished_at, status,
                    products_seen, products_skipped, products_marked_unavailable, error
             FROM ingestion_run',
        );
        self::assertIsArray($row);

        return $row;
    }
}
