<?php

declare(strict_types=1);

namespace App\Catalog\Application\ListSupermarkets;

use App\Catalog\Domain\ValueObject\SupermarketId;

interface SupermarketNames
{
    /**
     * @param list<SupermarketId> $supermarkets
     *
     * @return array<string, string> name by slug, only for the supermarkets in the catalog, in no particular order
     */
    public function namesOf(array $supermarkets): array;
}
