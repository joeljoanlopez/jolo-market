<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Catalog;

use App\Catalog\Application\ListSupermarkets\ListSupermarketsHandler;
use App\Receipts\Application\ReceiptSupermarkets;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Receipts\Infrastructure\Catalog\CatalogReceiptSupermarkets;
use App\Tests\Support\Catalog\FixedEnabledSupermarkets;
use App\Tests\Support\Catalog\InMemorySupermarketNames;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogReceiptSupermarketsTest extends KernelTestCase
{
    #[Test]
    public function the_enabled_supermarkets_and_their_names_come_from_the_catalog_query_api(): void
    {
        $supermarkets = new CatalogReceiptSupermarkets(new ListSupermarketsHandler(
            new FixedEnabledSupermarkets(['mercadona', 'aldi']),
            new InMemorySupermarketNames(['mercadona' => 'Mercadona', 'aldi' => 'Aldi', 'bonpreu' => 'Bonpreu i Esclat']),
        ));

        self::assertTrue($supermarkets->isEnabled(SupermarketId::fromString('aldi')));
        self::assertFalse($supermarkets->isEnabled(SupermarketId::fromString('bonpreu')));
        self::assertSame(['mercadona' => 'Mercadona'], $supermarkets->namesOf([SupermarketId::fromString('mercadona'), SupermarketId::fromString('bonpreu')]));
    }

    #[Test]
    public function it_is_the_supermarkets_the_application_gets(): void
    {
        self::assertInstanceOf(CatalogReceiptSupermarkets::class, self::getContainer()->get(ReceiptSupermarkets::class));
    }
}
