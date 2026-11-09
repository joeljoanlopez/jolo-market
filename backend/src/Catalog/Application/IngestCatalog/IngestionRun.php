<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * The record of one ingestion of one supermarket: when it ran, how it ended and what it saw.
 */
final class IngestionRun
{
    private IngestionRunStatus $status = IngestionRunStatus::RUNNING;

    private ?\DateTimeImmutable $finishedAt = null;

    private int $productsSeen = 0;

    private int $productsSkipped = 0;

    private int $productsMarkedUnavailable = 0;

    private ?string $error = null;

    private function __construct(
        private readonly Uuid $id,
        private readonly SupermarketId $supermarketId,
        private readonly \DateTimeImmutable $startedAt,
    ) {
    }

    public static function start(Uuid $id, SupermarketId $supermarketId, \DateTimeImmutable $startedAt): self
    {
        return new self($id, $supermarketId, $startedAt);
    }

    public function succeed(\DateTimeImmutable $finishedAt, int $productsSeen, int $productsSkipped, int $productsMarkedUnavailable): void
    {
        $this->finish(IngestionRunStatus::SUCCEEDED, $finishedAt, $productsSeen, $productsSkipped);
        $this->productsMarkedUnavailable = $productsMarkedUnavailable;
    }

    public function fail(\DateTimeImmutable $finishedAt, int $productsSeen, int $productsSkipped, string $error): void
    {
        $this->finish(IngestionRunStatus::FAILED, $finishedAt, $productsSeen, $productsSkipped);
        $this->error = $error;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function supermarketId(): SupermarketId
    {
        return $this->supermarketId;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function status(): IngestionRunStatus
    {
        return $this->status;
    }

    public function productsSeen(): int
    {
        return $this->productsSeen;
    }

    public function productsSkipped(): int
    {
        return $this->productsSkipped;
    }

    public function productsMarkedUnavailable(): int
    {
        return $this->productsMarkedUnavailable;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    private function finish(IngestionRunStatus $status, \DateTimeImmutable $finishedAt, int $productsSeen, int $productsSkipped): void
    {
        if (IngestionRunStatus::RUNNING !== $this->status) {
            throw new \LogicException(\sprintf('Ingestion run "%s" has already finished.', $this->id->value()));
        }

        $this->status = $status;
        $this->finishedAt = $finishedAt;
        $this->productsSeen = $productsSeen;
        $this->productsSkipped = $productsSkipped;
    }
}
