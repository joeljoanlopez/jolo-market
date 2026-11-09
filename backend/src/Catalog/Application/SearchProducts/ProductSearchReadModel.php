<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Full-text search over the available products (PD-22), ordered as PD-33 says.
 */
interface ProductSearchReadModel
{
    /**
     * Every word of $text must match the start of a word of the name or brand, ignoring case and accents.
     *
     * @param list<SupermarketId> $supermarkets the only supermarkets to search
     */
    public function search(string $text, array $supermarkets, int $page, int $perPage): ProductSearchPage;
}
