<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Ingestion;

use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Ingestion\EnvIngestionSettings;
use App\Shared\Domain\Exception\InvalidArgument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvIngestionSettingsTest extends TestCase
{
    #[Test]
    public function it_reads_the_instance_postal_code(): void
    {
        self::assertSame('08001', new EnvIngestionSettings('08001', 'mercadona')->postalCode()->value());
    }

    #[Test]
    public function it_rejects_a_missing_postal_code(): void
    {
        $this->expectException(InvalidArgument::class);

        new EnvIngestionSettings('', 'mercadona')->postalCode();
    }

    #[Test]
    public function it_reads_the_enabled_supermarkets_in_order_ignoring_blanks_and_repetitions(): void
    {
        $settings = new EnvIngestionSettings('08001', ' mercadona, aldi,,mercadona ,bonpreu ');

        $slugs = array_map(static fn (SupermarketId $id): string => $id->value(), $settings->enabledSupermarkets());

        self::assertSame(['mercadona', 'aldi', 'bonpreu'], $slugs);
    }

    #[Test]
    public function it_allows_no_enabled_supermarkets(): void
    {
        self::assertSame([], new EnvIngestionSettings('08001', '')->enabledSupermarkets());
    }

    #[Test]
    public function it_rejects_an_enabled_supermarket_that_is_not_a_slug(): void
    {
        $this->expectException(InvalidArgument::class);

        new EnvIngestionSettings('08001', 'Mercadona')->enabledSupermarkets();
    }
}
