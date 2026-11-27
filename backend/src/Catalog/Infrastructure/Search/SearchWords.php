<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

/**
 * The words that search matches, for product names, the dictionary and what the user types alike: lowercase, without
 * accents (so "platano" finds "Plátano", as MySQL's ai_ci collation did), split on anything that is not a letter or a
 * digit. The Catalan geminated l ("col·liflor", also written "col.liflor") stays one word.
 *
 * The index stores every prefix of every word, so matching a typed word exactly against them is matching "the start
 * of a word" (PD-33) for every word of the query, not only the last one.
 */
final class SearchWords
{
    /**
     * @return list<string> each word once, in order
     */
    public static function of(string $text): array
    {
        $folded = self::fold($text);
        $folded = preg_replace('/l[·.]l/u', 'll', $folded) ?? $folded;
        $words = preg_split('/[^\p{L}\p{N}]+/u', $folded, -1, \PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(false === $words ? [] : $words));
    }

    /**
     * @return list<string> each prefix once, the shortest first within each word
     */
    public static function prefixesOf(string $text): array
    {
        $prefixes = [];
        foreach (self::of($text) as $word) {
            $length = mb_strlen($word);
            for ($size = 1; $size <= $length; ++$size) {
                $prefixes[mb_substr($word, 0, $size)] = true;
            }
        }

        return array_map(strval(...), array_keys($prefixes));
    }

    private static function fold(string $text): string
    {
        $lowercase = mb_strtolower($text);
        $decomposed = \Normalizer::normalize($lowercase, \Normalizer::FORM_D);
        if (!\is_string($decomposed)) {
            return $lowercase;
        }

        return preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $decomposed;
    }
}
