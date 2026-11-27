<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Search;

use App\Catalog\Infrastructure\Search\InvalidSynonymFile;
use App\Catalog\Infrastructure\Search\SynonymFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SynonymFileTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../../Fixtures/Search/';

    #[Test]
    public function it_reads_each_group_of_equivalent_words_in_lowercase(): void
    {
        self::assertSame(
            [['leche', 'llet'], ['huevo', 'huevos', 'ou', 'ous']],
            new SynonymFile(self::FIXTURES.'valid.yaml')->groups(),
        );
    }

    #[Test]
    public function the_shipped_dictionary_loads_and_holds_the_basics_of_each_category(): void
    {
        $groups = new SynonymFile(__DIR__.'/../../../../../config/search/synonyms.yaml')->groups();

        // One entry per frequent word, with its translation and plurals (PD-102).
        self::assertGreaterThanOrEqual(150, \count($groups));
        self::assertLessThanOrEqual(250, \count($groups));
        foreach ([['leche', 'llet'], ['pan', 'pa'], ['huevo', 'ou'], ['aceite', 'oli']] as [$spanish, $catalan]) {
            self::assertContains([$spanish, $catalan], array_map(
                static fn (array $group): array => \in_array($spanish, $group, true) && \in_array($catalan, $group, true) ? [$spanish, $catalan] : [],
                $groups,
            ));
        }
    }

    #[Test]
    public function it_keeps_one_spelling_of_the_words_of_a_group_that_differ_only_by_their_accents(): void
    {
        self::assertSame([['ecológica', 'bio']], new SynonymFile(self::FIXTURES.'same-word-in-one-group.yaml')->groups());
    }

    #[Test]
    public function it_reads_a_file_without_groups(): void
    {
        self::assertSame([], new SynonymFile(self::FIXTURES.'no-groups.yaml')->groups());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'empty group' => ['empty-group.yaml', 'group 2 is empty'];
        yield 'group of one word' => ['one-word.yaml', 'group 1 has only one word ("leche")'];
        yield 'word with spaces' => ['word-with-spaces.yaml', '"aceite de oliva" in group 1 has spaces'];
        yield 'word in two groups' => ['word-in-two-groups.yaml', '"llet" is in groups 1 and 2'];
        yield 'not a single search word' => ['not-a-single-word.yaml', '"semi-desnatada" in group 1 is not a single word'];
        yield 'same word but for its accents' => ['same-word-with-accents.yaml', '"platano" is in groups 1 and 2'];
        yield 'not a list' => ['not-a-list.yaml', 'it must be a list of groups'];
        yield 'not a word' => ['not-a-word.yaml', 'group 1 must be a list of words'];
        yield 'malformed YAML' => ['malformed.yaml', 'it is not valid YAML'];
        yield 'missing file' => ['missing.yaml', 'the file cannot be read'];
    }

    #[Test]
    #[DataProvider('invalidFiles')]
    public function it_rejects_an_invalid_file_with_a_clear_message(string $file, string $reason): void
    {
        $this->expectException(InvalidSynonymFile::class);
        $this->expectExceptionMessage(\sprintf('The synonym file %s is invalid: %s.', self::FIXTURES.$file, $reason));

        new SynonymFile(self::FIXTURES.$file)->groups();
    }
}
