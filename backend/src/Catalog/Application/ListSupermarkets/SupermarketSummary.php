<?php

declare(strict_types=1);

namespace App\Catalog\Application\ListSupermarkets;

final readonly class SupermarketSummary
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
