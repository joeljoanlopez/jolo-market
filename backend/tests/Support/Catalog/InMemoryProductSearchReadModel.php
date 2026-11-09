<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\SearchProducts\ProductSearchPage;
use App\Catalog\Application\SearchProducts\ProductSearchReadModel;
use App\Catalog\Application\SearchProducts\ProductSearchResult;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Holds available products only. Matching is a case-insensitive substring of every word, and results keep
 * the order they were added in: accent folding and the PD-33 ordering are the DBAL adapter's job,
 * covered by its integration tests.
 */
final class InMemoryProductSearchReadModel implements ProductSearchReadModel
{
    /** @var list<ProductSearchResult> */
    private array $results = [];

    public function add(ProductSearchResult $result): void
    {
        $this->results[] = $result;
    }

    public function search(string $text, array $supermarkets, int $page, int $perPage): ProductSearchPage
    {
        $slugs = array_map(static fn (SupermarketId $id): string => $id->value(), $supermarkets);
        $words = array_filter(explode(' ', mb_strtolower($text)));
        $matches = array_values(array_filter(
            $this->results,
            static fn (ProductSearchResult $result): bool => \in_array($result->supermarketId, $slugs, true)
                && [] === array_filter($words, static fn (string $word): bool => !str_contains(mb_strtolower($result->name.' '.$result->brand), $word)),
        ));

        $cheapest = [];
        foreach ($matches as $match) {
            if (null !== $match->unitPriceUnit && null !== $match->unitPriceCents) {
                $cheapest[$match->unitPriceUnit] = min($cheapest[$match->unitPriceUnit] ?? \PHP_INT_MAX, $match->unitPriceCents);
            }
        }

        return new ProductSearchPage(
            \array_slice($matches, ($page - 1) * $perPage, $perPage),
            \count($matches),
            $page,
            $perPage,
            $cheapest,
        );
    }
}
