<?php

declare(strict_types=1);

namespace App\Catalog\Application\SuggestProducts;

use App\Catalog\Domain\Service\WordPrefixSimilarity;
use App\Catalog\Domain\ValueObject\InvalidSupermarketId;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Query API for other contexts (E10.7): the products of a supermarket most similar to a text
 * printed on a receipt, available or not (PD-67). Similarity is the number of words that match
 * the start of a word in the name or brand; ties go by name.
 */
final readonly class SuggestProductsHandler
{
    public function __construct(private SuggestionCandidates $candidates)
    {
    }

    /**
     * @return list<ProductSuggestion> the most similar first; empty when nothing matches
     */
    public function __invoke(SuggestProductsQuery $query): array
    {
        $words = WordPrefixSimilarity::wordsOf($query->text);
        if ([] === $words || $query->limit < 1) {
            return [];
        }
        try {
            $supermarketId = SupermarketId::fromString($query->supermarketId);
        } catch (InvalidSupermarketId) {
            return [];
        }

        $scored = [];
        foreach ($this->candidates->matchingAnyWord($supermarketId, $words) as $candidate) {
            $score = WordPrefixSimilarity::score($words, $candidate->name, $candidate->brand);
            if ($score > 0) {
                $scored[] = ['score' => $score, 'candidate' => $candidate];
            }
        }

        usort($scored, static fn (array $a, array $b): int => [$b['score'], $a['candidate']->name, $a['candidate']->brand ?? '', $a['candidate']->productId]
            <=> [$a['score'], $b['candidate']->name, $b['candidate']->brand ?? '', $b['candidate']->productId]);

        return array_map(
            static fn (array $entry): ProductSuggestion => new ProductSuggestion($entry['candidate']->productId, $entry['candidate']->name, $entry['candidate']->brand, $entry['candidate']->available),
            \array_slice($scored, 0, $query->limit),
        );
    }
}
