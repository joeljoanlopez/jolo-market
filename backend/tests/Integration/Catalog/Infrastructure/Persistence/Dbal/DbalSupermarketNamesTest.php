<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Dbal;

use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Catalog\Infrastructure\Persistence\Dbal\DbalSupermarketNames;
use PHPUnit\Framework\Attributes\Test;

final class DbalSupermarketNamesTest extends DbalTestCase
{
    #[Test]
    public function it_returns_the_names_of_the_given_supermarkets_by_slug(): void
    {
        $names = new DbalSupermarketNames($this->connection)->namesOf([SupermarketId::fromString('bonpreu'), SupermarketId::fromString('aldi'), SupermarketId::fromString('lidl')]);

        self::assertEquals(['bonpreu' => 'Bonpreu i Esclat', 'aldi' => 'Aldi'], $names);
    }

    #[Test]
    public function it_returns_no_names_for_no_supermarkets(): void
    {
        self::assertSame([], new DbalSupermarketNames($this->connection)->namesOf([]));
    }
}
