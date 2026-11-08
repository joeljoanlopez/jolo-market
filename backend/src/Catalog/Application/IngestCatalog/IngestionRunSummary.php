<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

final readonly class IngestionRunSummary
{
    public function __construct(
        public string $supermarket,
        public string $status,
        public int $productsSeen,
        public int $productsSkipped,
        public int $productsMarkedUnavailable,
        public ?string $error,
    ) {
    }

    public static function of(IngestionRun $run): self
    {
        return new self(
            $run->supermarketId()->value(),
            $run->status()->value,
            $run->productsSeen(),
            $run->productsSkipped(),
            $run->productsMarkedUnavailable(),
            $run->error(),
        );
    }
}
