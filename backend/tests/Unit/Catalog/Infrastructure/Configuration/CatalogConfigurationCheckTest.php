<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Configuration;

use App\Catalog\Infrastructure\Configuration\CatalogConfigurationCheck;
use App\Tests\Support\Catalog\FakeCatalogSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogConfigurationCheckTest extends TestCase
{
    #[Test]
    public function it_accepts_a_valid_postal_code_and_supermarkets_that_have_an_adapter(): void
    {
        self::assertSame([], $this->check('08001', 'mercadona, aldi')->problems());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function missingValues(): iterable
    {
        yield 'unset' => [null];
        yield 'empty' => [''];
        yield 'blank' => ['  '];
    }

    #[Test]
    #[DataProvider('missingValues')]
    public function it_reports_a_missing_postal_code(?string $postalCode): void
    {
        self::assertSame(
            ['INSTANCE_POSTAL_CODE is missing: set the Spanish postal code whose prices are imported, for example 08001.'],
            $this->check($postalCode, 'mercadona')->problems(),
        );
    }

    #[Test]
    public function it_reports_an_invalid_postal_code(): void
    {
        self::assertSame(
            ['INSTANCE_POSTAL_CODE is invalid: "99999" is not a valid Spanish postal code.'],
            $this->check('99999', 'mercadona')->problems(),
        );
    }

    #[Test]
    #[DataProvider('missingValues')]
    public function it_reports_missing_supermarkets(?string $supermarkets): void
    {
        self::assertSame(
            ['ENABLED_SUPERMARKETS is missing: set a comma-separated list of supermarkets, among aldi, mercadona.'],
            $this->check('08001', $supermarkets)->problems(),
        );
    }

    #[Test]
    public function it_reports_a_supermarket_that_is_not_a_slug(): void
    {
        self::assertSame(
            ['ENABLED_SUPERMARKETS is invalid: "Mercadona" is not a valid supermarket id (lowercase letters, digits and inner hyphens).'],
            $this->check('08001', 'Mercadona')->problems(),
        );
    }

    #[Test]
    public function it_reports_a_supermarket_without_an_adapter(): void
    {
        self::assertSame(
            ['ENABLED_SUPERMARKETS is invalid: "mercadna" has no catalog adapter; use aldi, mercadona.'],
            $this->check('08001', 'aldi,mercadna')->problems(),
        );
    }

    #[Test]
    public function it_reports_every_problem_at_once(): void
    {
        self::assertCount(2, $this->check(null, null)->problems());
    }

    private function check(?string $postalCode, ?string $supermarkets): CatalogConfigurationCheck
    {
        return new CatalogConfigurationCheck(
            $postalCode,
            $supermarkets,
            [new FakeCatalogSource('mercadona', []), new FakeCatalogSource('aldi', [])],
        );
    }
}
