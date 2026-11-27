<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The Spanish ↔ Catalan dictionary (PD-101): a YAML list of groups of equivalent words, such as `- [leche, llet]`.
 * It is read on every reindex, so a change takes effect without a code change.
 */
final readonly class SynonymFile
{
    public function __construct(private string $path)
    {
    }

    /**
     * @return list<non-empty-list<string>> each group has two or more lowercase words, and no word is in two groups
     *
     * @throws InvalidSynonymFile
     */
    public function groups(): array
    {
        $groups = [];
        $groupOfWord = [];
        foreach ($this->rawGroups() as $index => $rawGroup) {
            $number = $index + 1;
            $group = $this->group($rawGroup, $number);
            foreach ($group as $word) {
                if (isset($groupOfWord[$word])) {
                    throw InvalidSynonymFile::because($this->path, \sprintf('"%s" is in groups %d and %d', $word, $groupOfWord[$word], $number));
                }
                $groupOfWord[$word] = $number;
            }
            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * @return list<mixed>
     */
    private function rawGroups(): array
    {
        $content = is_file($this->path) ? @file_get_contents($this->path) : false;
        if (false === $content) {
            throw InvalidSynonymFile::because($this->path, 'the file cannot be read');
        }

        try {
            $groups = Yaml::parse($content);
        } catch (ParseException) {
            throw InvalidSynonymFile::because($this->path, 'it is not valid YAML');
        }

        if (!\is_array($groups) || !array_is_list($groups)) {
            throw InvalidSynonymFile::because($this->path, 'it must be a list of groups');
        }

        return $groups;
    }

    /**
     * @return non-empty-list<string>
     */
    private function group(mixed $rawGroup, int $number): array
    {
        if (!\is_array($rawGroup) || !array_is_list($rawGroup)) {
            throw InvalidSynonymFile::because($this->path, \sprintf('group %d must be a list of words', $number));
        }

        $words = [];
        foreach ($rawGroup as $word) {
            if (!\is_string($word)) {
                throw InvalidSynonymFile::because($this->path, \sprintf('group %d must be a list of words', $number));
            }
            $word = mb_strtolower(trim($word));
            if (1 === preg_match('/\s/u', $word)) {
                throw InvalidSynonymFile::because($this->path, \sprintf('"%s" in group %d has spaces', $word, $number));
            }
            if ('' !== $word && !\in_array($word, $words, true)) {
                $words[] = $word;
            }
        }

        if ([] === $words) {
            throw InvalidSynonymFile::because($this->path, \sprintf('group %d is empty', $number));
        }
        if (1 === \count($words)) {
            throw InvalidSynonymFile::because($this->path, \sprintf('group %d has only one word ("%s")', $number, $words[0]));
        }

        return $words;
    }
}
