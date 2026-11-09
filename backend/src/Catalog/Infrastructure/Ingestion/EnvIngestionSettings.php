<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Ingestion;

use App\Catalog\Application\IngestCatalog\IngestionSettings;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Reads INSTANCE_POSTAL_CODE and ENABLED_SUPERMARKETS. Values are validated when used,
 * so a wrong value fails the ingestion command with a clear message instead of the whole container.
 */
final readonly class EnvIngestionSettings implements IngestionSettings
{
    public function __construct(
        private string $postalCode,
        private string $enabledSupermarkets,
    ) {
    }

    public function postalCode(): PostalCode
    {
        return PostalCode::fromString($this->postalCode);
    }

    public function enabledSupermarkets(): array
    {
        $slugs = array_unique(array_filter(
            array_map(trim(...), explode(',', $this->enabledSupermarkets)),
            static fn (string $slug): bool => '' !== $slug,
        ));

        return array_values(array_map(SupermarketId::fromString(...), $slugs));
    }
}
