<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

use App\Catalog\Domain\ValueObject\PostalCode;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Instance configuration for ingestion: INSTANCE_POSTAL_CODE (PD-5) and ENABLED_SUPERMARKETS (PD-24).
 */
interface IngestionSettings
{
    public function postalCode(): PostalCode;

    /**
     * @return list<SupermarketId> in the order they are ingested
     */
    public function enabledSupermarkets(): array;
}
