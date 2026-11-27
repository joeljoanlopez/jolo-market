<?php

declare(strict_types=1);

namespace App\Catalog\Application\SuggestProducts;

final readonly class SuggestionCandidate
{
    public function __construct(
        public string $productId,
        public string $name,
        public ?string $brand,
        public bool $available,
    ) {
    }
}
