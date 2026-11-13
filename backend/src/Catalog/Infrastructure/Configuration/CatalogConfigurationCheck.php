<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Configuration;

use App\Catalog\Domain\Port\SupermarketCatalogSource;
use App\Catalog\Infrastructure\Ingestion\EnvIngestionSettings;
use App\Shared\Domain\Exception\InvalidArgument;
use App\Shared\Infrastructure\Configuration\ConfigurationCheck;

/**
 * INSTANCE_POSTAL_CODE and ENABLED_SUPERMARKETS, parsed the same way as ingestion and search do (EnvIngestionSettings).
 * A supermarket without an adapter would only fail at the next ingestion, so it is caught here too.
 */
final readonly class CatalogConfigurationCheck implements ConfigurationCheck
{
    /** @var list<string> sorted slugs of the supermarkets that have an adapter */
    private array $supported;

    /**
     * @param iterable<SupermarketCatalogSource> $sources
     */
    public function __construct(
        private ?string $postalCode,
        private ?string $enabledSupermarkets,
        iterable $sources,
    ) {
        $supported = [];
        foreach ($sources as $source) {
            $supported[] = $source->supermarketId()->value();
        }
        sort($supported);
        $this->supported = $supported;
    }

    public function problems(): array
    {
        $settings = new EnvIngestionSettings($this->postalCode ?? '', $this->enabledSupermarkets ?? '');

        return [...$this->postalCodeProblems($settings), ...$this->supermarketProblems($settings)];
    }

    /**
     * @return list<string>
     */
    private function postalCodeProblems(EnvIngestionSettings $settings): array
    {
        if ('' === trim($this->postalCode ?? '')) {
            return ['INSTANCE_POSTAL_CODE is missing: set the Spanish postal code whose prices are imported, for example 08001.'];
        }

        try {
            $settings->postalCode();
        } catch (InvalidArgument $invalid) {
            return ['INSTANCE_POSTAL_CODE is invalid: '.$invalid->getMessage()];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function supermarketProblems(EnvIngestionSettings $settings): array
    {
        $supported = implode(', ', $this->supported);

        try {
            $enabled = $settings->enabledSupermarkets();
        } catch (InvalidArgument $invalid) {
            return ['ENABLED_SUPERMARKETS is invalid: '.$invalid->getMessage()];
        }

        if ([] === $enabled) {
            return [\sprintf('ENABLED_SUPERMARKETS is missing: set a comma-separated list of supermarkets, among %s.', $supported)];
        }

        $problems = [];
        foreach ($enabled as $supermarketId) {
            if (!\in_array($supermarketId->value(), $this->supported, true)) {
                $problems[] = \sprintf('ENABLED_SUPERMARKETS is invalid: "%s" has no catalog adapter; use %s.', $supermarketId->value(), $supported);
            }
        }

        return $problems;
    }
}
