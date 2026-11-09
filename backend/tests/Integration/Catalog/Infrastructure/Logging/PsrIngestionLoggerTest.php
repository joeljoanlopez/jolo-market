<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Logging;

use App\Catalog\Application\IngestCatalog\IngestionRun;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Logging\PsrIngestionLogger;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class PsrIngestionLoggerTest extends TestCase
{
    #[Test]
    public function it_logs_a_skipped_product_as_a_warning_with_structured_context(): void
    {
        $psrLogger = new InMemoryLogger();

        new PsrIngestionLogger($psrLogger)->productSkipped(SupermarketId::fromString('aldi'), 'A-7', 'A product name cannot be blank.');

        self::assertSame([[
            'level' => LogLevel::WARNING,
            'message' => 'Skipped a malformed product.',
            'context' => ['supermarket' => 'aldi', 'external_id' => 'A-7', 'reason' => 'A product name cannot be blank.'],
        ]], $psrLogger->records);
    }

    #[Test]
    public function it_logs_a_successful_run_as_info_with_its_counts_and_duration(): void
    {
        $psrLogger = new InMemoryLogger();
        $run = self::startedRun();
        $run->succeed(new \DateTimeImmutable('2026-11-08 06:02:03.500', new \DateTimeZone('UTC')), 120, 2, 5);

        new PsrIngestionLogger($psrLogger)->runFinished($run);

        self::assertSame([[
            'level' => LogLevel::INFO,
            'message' => 'Catalog ingestion run finished.',
            'context' => [
                'run_id' => '00000000-0000-4000-8000-000000000001',
                'supermarket' => 'mercadona',
                'status' => 'succeeded',
                'products_seen' => 120,
                'products_skipped' => 2,
                'products_marked_unavailable' => 5,
                'duration_ms' => 123500,
                'error' => null,
            ],
        ]], $psrLogger->records);
    }

    #[Test]
    public function it_logs_a_failed_run_as_an_error(): void
    {
        $psrLogger = new InMemoryLogger();
        $run = self::startedRun();
        $run->fail(new \DateTimeImmutable('2026-11-08 06:00:01', new \DateTimeZone('UTC')), 0, 0, 'HTTP 503.');

        new PsrIngestionLogger($psrLogger)->runFinished($run);

        self::assertSame(LogLevel::ERROR, $psrLogger->records[0]['level']);
        self::assertSame('HTTP 503.', $psrLogger->records[0]['context']['error']);
    }

    private static function startedRun(): IngestionRun
    {
        return IngestionRun::start(
            Uuid::fromString('00000000-0000-4000-8000-000000000001'),
            SupermarketId::fromString('mercadona'),
            new \DateTimeImmutable('2026-11-08 06:00:00', new \DateTimeZone('UTC')),
        );
    }
}
