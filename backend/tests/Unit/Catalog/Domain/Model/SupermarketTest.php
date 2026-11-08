<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Supermarket;
use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\Exception\InvalidArgument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupermarketTest extends TestCase
{
    #[Test]
    public function it_is_created_with_an_id_a_name_and_whether_it_is_enabled(): void
    {
        $supermarket = Supermarket::create(SupermarketId::fromString('bonpreu'), 'Bonpreu i Esclat', true);

        self::assertTrue(SupermarketId::fromString('bonpreu')->equals($supermarket->id()));
        self::assertSame('Bonpreu i Esclat', $supermarket->name());
        self::assertTrue($supermarket->isEnabled());
    }

    #[Test]
    public function it_can_be_disabled(): void
    {
        $supermarket = Supermarket::create(SupermarketId::fromString('aldi'), 'Aldi', false);

        self::assertFalse($supermarket->isEnabled());
    }

    #[Test]
    public function it_rejects_a_blank_name(): void
    {
        $this->expectException(InvalidArgument::class);

        Supermarket::create(SupermarketId::fromString('aldi'), '  ', true);
    }
}
