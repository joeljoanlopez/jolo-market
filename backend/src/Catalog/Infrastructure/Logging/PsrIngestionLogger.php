<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Logging;

use App\Catalog\Application\IngestCatalog\IngestionLogger;
use App\Catalog\Application\IngestCatalog\IngestionRun;
use App\Catalog\Application\IngestCatalog\IngestionRunStatus;
use App\Catalog\Domain\ValueObject\SupermarketId;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

/**
 * Structured logs on the "ingestion" channel: the context carries every value, the message is fixed.
 */
#[WithMonologChannel('ingestion')]
final readonly class PsrIngestionLogger implements IngestionLogger
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function productSkipped(SupermarketId $supermarketId, string $externalId, string $reason): void
    {
        $this->logger->warning('Skipped a malformed product.', [
            'supermarket' => $supermarketId->value(),
            'external_id' => $externalId,
            'reason' => $reason,
        ]);
    }

    public function runFinished(IngestionRun $run): void
    {
        $context = [
            'run_id' => $run->id()->value(),
            'supermarket' => $run->supermarketId()->value(),
            'status' => $run->status()->value,
            'products_seen' => $run->productsSeen(),
            'products_skipped' => $run->productsSkipped(),
            'products_marked_unavailable' => $run->productsMarkedUnavailable(),
            'duration_ms' => self::durationInMilliseconds($run),
            'error' => $run->error(),
        ];

        if (IngestionRunStatus::SUCCEEDED === $run->status()) {
            $this->logger->info('Catalog ingestion run finished.', $context);
        } else {
            $this->logger->error('Catalog ingestion run finished.', $context);
        }
    }

    private static function durationInMilliseconds(IngestionRun $run): ?int
    {
        $finishedAt = $run->finishedAt();
        if (null === $finishedAt) {
            return null;
        }

        $microseconds = (int) $finishedAt->format('Uu') - (int) $run->startedAt()->format('Uu');

        return intdiv($microseconds, 1000);
    }
}
