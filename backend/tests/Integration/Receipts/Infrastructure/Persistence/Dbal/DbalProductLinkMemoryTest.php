<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Persistence\Dbal;

use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use App\Receipts\Infrastructure\Persistence\Dbal\DbalProductLinkMemory;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\Test;

final class DbalProductLinkMemoryTest extends ReceiptsDbalTestCase
{
    #[Test]
    public function it_remembers_a_text_per_supermarket_ignoring_case_and_repeated_spaces(): void
    {
        $memory = $this->memory();

        $memory->remember(SupermarketId::fromString('mercadona'), LineText::fromString('LECHE  ENT.'), ProductId::fromString(self::MILK));

        self::assertSame(self::MILK, $memory->find(SupermarketId::fromString('mercadona'), LineText::fromString('leche ent.'))?->value());
        self::assertNull($memory->find(SupermarketId::fromString('aldi'), LineText::fromString('LECHE ENT.')));
        self::assertNull($memory->find(SupermarketId::fromString('mercadona'), LineText::fromString('LECHE')));
    }

    #[Test]
    public function accents_still_matter(): void
    {
        $memory = $this->memory();

        $memory->remember(SupermarketId::fromString('mercadona'), LineText::fromString('PÀ'), ProductId::fromString(self::BREAD));

        self::assertNull($memory->find(SupermarketId::fromString('mercadona'), LineText::fromString('PA')));
        self::assertSame(self::BREAD, $memory->find(SupermarketId::fromString('mercadona'), LineText::fromString('pà'))?->value());
    }

    #[Test]
    public function the_last_one_remembered_wins(): void
    {
        $memory = $this->memory();

        $memory->remember(SupermarketId::fromString('mercadona'), LineText::fromString('PAN'), ProductId::fromString(self::MILK));
        $memory->remember(SupermarketId::fromString('mercadona'), LineText::fromString('pan'), ProductId::fromString(self::BREAD));

        self::assertSame(self::BREAD, $memory->find(SupermarketId::fromString('mercadona'), LineText::fromString('PAN'))?->value());
        self::assertSame(1, $this->countRows('receipt_product_link'));
    }

    private function memory(): DbalProductLinkMemory
    {
        return new DbalProductLinkMemory($this->connection, FixedClock::at('2026-11-14 18:00:00'));
    }
}
