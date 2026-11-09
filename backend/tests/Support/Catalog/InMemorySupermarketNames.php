<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\ListSupermarkets\SupermarketNames;
use App\Catalog\Domain\ValueObject\SupermarketId;

final readonly class InMemorySupermarketNames implements SupermarketNames
{
    /**
     * @param array<string, string> $names name by slug
     */
    public function __construct(private array $names)
    {
    }

    public function namesOf(array $supermarkets): array
    {
        $slugs = array_map(static fn (SupermarketId $id): string => $id->value(), $supermarkets);

        return array_intersect_key($this->names, array_flip($slugs));
    }
}
