<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\ValueObject;

use App\Receipts\Domain\ValueObject\InvalidSupermarketId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupermarketIdTest extends TestCase
{
    #[Test]
    public function it_is_a_slug(): void
    {
        self::assertSame('bonpreu-esclat', SupermarketId::fromString('bonpreu-esclat')->value());
        self::assertTrue(SupermarketId::fromString('aldi')->equals(SupermarketId::fromString('aldi')));
    }

    #[Test]
    public function it_rejects_anything_but_a_slug(): void
    {
        $this->expectException(InvalidSupermarketId::class);

        SupermarketId::fromString('Mercadona!');
    }
}
