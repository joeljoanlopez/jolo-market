<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\IngestCatalog\IngestionSettings;
use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;

final readonly class FixedIngestionSettings implements IngestionSettings
{
    /**
     * @param list<string> $enabledSupermarkets
     */
    public function __construct(
        private string $postalCode,
        private array $enabledSupermarkets,
    ) {
    }

    public function postalCode(): PostalCode
    {
        return PostalCode::fromString($this->postalCode);
    }

    public function enabledSupermarkets(): array
    {
        return array_map(SupermarketId::fromString(...), $this->enabledSupermarkets);
    }
}
