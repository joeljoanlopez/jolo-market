<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

/**
 * How similar a receipt text is to a product (PD-67): the number of its words that match the start
 * of a word in the product's name or brand, so abbreviations such as "ENT." match "Entera".
 * Case, accents and punctuation do not matter.
 */
final class WordPrefixSimilarity
{
    /**
     * @return list<string> lowercase words without accents, each once, in order
     */
    public static function wordsOf(string $text): array
    {
        $lowercase = mb_strtolower($text);
        $decomposed = \Normalizer::normalize($lowercase, \Normalizer::FORM_D);
        $folded = \is_string($decomposed) ? (preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $decomposed) : $lowercase;
        $words = preg_split('/[^\p{L}\p{N}]+/u', $folded, -1, \PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(false === $words ? [] : $words));
    }

    /**
     * @param list<string> $queryWords as given by wordsOf()
     */
    public static function score(array $queryWords, string $name, ?string $brand): int
    {
        $productWords = self::wordsOf($name.' '.($brand ?? ''));

        $score = 0;
        foreach ($queryWords as $queryWord) {
            foreach ($productWords as $productWord) {
                if (str_starts_with($productWord, $queryWord)) {
                    ++$score;
                    break;
                }
            }
        }

        return $score;
    }
}
