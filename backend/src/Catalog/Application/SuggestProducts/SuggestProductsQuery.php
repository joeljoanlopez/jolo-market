<?php

declare(strict_types=1);

namespace App\Catalog\Application\SuggestProducts;

final readonly class SuggestProductsQuery
{
    public const int DEFAULT_LIMIT = 5;

    public function __construct(
        public string $supermarketId,
        public string $text,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
    }
}
