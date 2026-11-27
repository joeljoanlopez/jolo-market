<?php

declare(strict_types=1);

namespace App\Catalog\Application\FindUnmappedCategories;

use App\Catalog\Domain\Model\Section;
use App\Catalog\Domain\Service\SectionMapping;
use App\Catalog\Domain\ValueObject\CategoryPath;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * The stored categories that the section mapping sends to "other" (PD-95), so the mapping can be
 * extended. It changes nothing.
 */
final readonly class FindUnmappedCategoriesHandler
{
    public function __construct(
        private StoredCategories $categories,
        private SectionMapping $sections,
    ) {
    }

    /**
     * @return list<CategoryCount> by supermarket id, then most products first, then by path
     */
    public function __invoke(FindUnmappedCategoriesQuery $query): array
    {
        $unmapped = array_values(array_filter(
            $this->categories->counts(),
            fn (CategoryCount $count): bool => Section::OTHER === $this->sections->sectionOf(
                SupermarketId::fromString($count->supermarketId),
                CategoryPath::fromString($count->categoryPath),
            ),
        ));

        usort($unmapped, static fn (CategoryCount $a, CategoryCount $b): int => [$a->supermarketId, $b->productCount, $a->categoryPath]
            <=> [$b->supermarketId, $a->productCount, $b->categoryPath]);

        return $unmapped;
    }
}
