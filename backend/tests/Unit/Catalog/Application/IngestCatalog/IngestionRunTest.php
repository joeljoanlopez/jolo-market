<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\IngestCatalog;

use App\Catalog\Application\IngestCatalog\IngestionRun;
use App\Catalog\Application\IngestCatalog\IngestionRunStatus;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IngestionRunTest extends TestCase
{
    #[Test]
    public function it_starts_as_running_with_no_counts(): void
    {
        $run = self::startedRun();

        self::assertSame(IngestionRunStatus::RUNNING, $run->status());
        self::assertNull($run->finishedAt());
        self::assertSame(0, $run->productsSeen());
        self::assertNull($run->error());
    }

    #[Test]
    public function it_cannot_finish_twice(): void
    {
        $run = self::startedRun();
        $run->succeed(new \DateTimeImmutable('2026-11-08 06:05'), 10, 0, 0);

        $this->expectException(\LogicException::class);

        $run->fail(new \DateTimeImmutable('2026-11-08 06:06'), 10, 0, 'Late failure.');
    }

    private static function startedRun(): IngestionRun
    {
        return IngestionRun::start(
            Uuid::fromString('00000000-0000-4000-8000-000000000001'),
            SupermarketId::fromString('mercadona'),
            new \DateTimeImmutable('2026-11-08 06:00'),
        );
    }
}
