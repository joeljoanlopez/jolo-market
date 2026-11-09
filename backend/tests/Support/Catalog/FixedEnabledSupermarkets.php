<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\SearchProducts\EnabledSupermarkets;
use App\Catalog\Domain\ValueObject\SupermarketId;

final readonly class FixedEnabledSupermarkets implements EnabledSupermarkets
{
    /**
     * @param list<string> $slugs
     */
    public function __construct(private array $slugs)
    {
    }

    public function enabledSupermarkets(): array
    {
        return array_map(SupermarketId::fromString(...), $this->slugs);
    }
}
