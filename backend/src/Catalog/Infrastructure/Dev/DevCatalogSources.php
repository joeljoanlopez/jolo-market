<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Dev;

use App\Catalog\Domain\Port\SupermarketCatalogSource;

/**
 * The catalog sources of the development environment (PD-53): the dummy ones unless
 * CATALOG_SOURCES is "real". The choice is read when ingestion starts, so changing the
 * variable needs no cache clear.
 *
 * @implements \IteratorAggregate<SupermarketCatalogSource>
 */
final readonly class DevCatalogSources implements \IteratorAggregate
{
    public const string DUMMY = 'dummy';
    public const string REAL = 'real';

    /**
     * @param iterable<SupermarketCatalogSource> $real
     * @param iterable<SupermarketCatalogSource> $dummy
     */
    public function __construct(
        private string $choice,
        private iterable $real,
        private iterable $dummy,
    ) {
    }

    public function getIterator(): \Generator
    {
        yield from match ($this->choice) {
            self::DUMMY => $this->dummy,
            self::REAL => $this->real,
            default => throw new \InvalidArgumentException(\sprintf('CATALOG_SOURCES must be "%s" or "%s", got "%s".', self::DUMMY, self::REAL, $this->choice)),
        };
    }
}
