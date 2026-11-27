<?php

declare(strict_types=1);

namespace App\Catalog\Application\SuggestProducts;

use App\Catalog\Domain\ValueObject\SupermarketId;

interface SuggestionCandidates
{
    /**
     * Products of the supermarket, available or not, that may match one of the words. It may return
     * more than the exact matches (the handler scores them), but never miss one that matches the
     * start of a word of the name or brand; the likeliest come first when it has to cut the list.
     *
     * @param non-empty-list<string> $words lowercase, without accents
     *
     * @return list<SuggestionCandidate>
     */
    public function matchingAnyWord(SupermarketId $supermarketId, array $words): array;
}
