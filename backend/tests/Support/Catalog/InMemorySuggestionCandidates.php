<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Application\SuggestProducts\SuggestionCandidate;
use App\Catalog\Application\SuggestProducts\SuggestionCandidates;
use App\Catalog\Domain\ValueObject\SupermarketId;

/**
 * Returns every product of the supermarket: the handler decides which ones match.
 */
final class InMemorySuggestionCandidates implements SuggestionCandidates
{
    /** @var array<string, list<SuggestionCandidate>> */
    private array $bySupermarket = [];

    public function add(string $supermarketId, SuggestionCandidate $candidate): void
    {
        $this->bySupermarket[$supermarketId][] = $candidate;
    }

    public function matchingAnyWord(SupermarketId $supermarketId, array $words): array
    {
        return $this->bySupermarket[$supermarketId->value()] ?? [];
    }
}
