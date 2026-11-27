<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\SuggestProducts;

use App\Catalog\Application\SuggestProducts\ProductSuggestion;
use App\Catalog\Application\SuggestProducts\SuggestionCandidate;
use App\Catalog\Application\SuggestProducts\SuggestProductsHandler;
use App\Catalog\Application\SuggestProducts\SuggestProductsQuery;
use App\Tests\Support\Catalog\InMemorySuggestionCandidates;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SuggestProductsHandlerTest extends TestCase
{
    private InMemorySuggestionCandidates $candidates;

    protected function setUp(): void
    {
        $this->candidates = new InMemorySuggestionCandidates();
    }

    #[Test]
    public function abbreviated_words_match_the_start_of_a_word_in_the_name_or_brand(): void
    {
        $this->add('1', 'mercadona', 'Leche entera', 'Hacendado');
        $this->add('2', 'mercadona', 'Leche semidesnatada', 'Hacendado');
        $this->add('3', 'mercadona', 'Pan de molde', 'Hacendado');

        self::assertSame(['1', '2', '3'], $this->suggest('mercadona', 'LECHE ENT. HACEND'));
    }

    #[Test]
    public function more_matching_words_rank_first_and_ties_go_by_name(): void
    {
        $this->add('1', 'mercadona', 'Yogur natural', null);
        $this->add('2', 'mercadona', 'Leche desnatada', 'Pascual');
        $this->add('3', 'mercadona', 'Leche desnatada', 'Asturiana');
        $this->add('4', 'mercadona', 'Bebida de avena', null);

        self::assertSame(['3', '2', '1'], $this->suggest('mercadona', 'LECHE DESN NATUR'));
    }

    #[Test]
    public function punctuation_and_accents_do_not_matter(): void
    {
        $this->add('1', 'aldi', 'Plátano de Canarias', null);
        $this->add('2', 'aldi', 'Café molido', 'Bellarom');

        self::assertSame(['1'], $this->suggest('aldi', 'PLATANO*CANARIAS'));
        self::assertSame(['2'], $this->suggest('aldi', 'cafè, mol.'));
    }

    #[Test]
    public function only_products_of_the_supermarket_are_suggested_available_or_not(): void
    {
        $this->add('1', 'mercadona', 'Leche entera', null, available: false);
        $this->add('2', 'aldi', 'Leche entera', null);

        $suggestions = ($this->handler())(new SuggestProductsQuery('mercadona', 'LECHE'));

        self::assertEquals([new ProductSuggestion('1', 'Leche entera', null, false)], $suggestions);
    }

    #[Test]
    public function a_word_inside_another_word_does_not_match(): void
    {
        $this->add('1', 'mercadona', 'Chocolate negro', null);

        self::assertSame([], $this->suggest('mercadona', 'COLATE'));
    }

    #[Test]
    public function it_suggests_nothing_when_no_word_matches_or_the_text_has_no_words(): void
    {
        $this->add('1', 'mercadona', 'Leche entera', null);

        self::assertSame([], $this->suggest('mercadona', 'DETERGENTE'));
        self::assertSame([], $this->suggest('mercadona', '*** 1,25'));
        self::assertSame([], $this->suggest('unknown', 'LECHE'));
    }

    #[Test]
    public function it_returns_at_most_the_limit(): void
    {
        foreach (range(1, 8) as $n) {
            $this->add((string) $n, 'mercadona', 'Leche '.$n, null);
        }

        self::assertCount(5, $this->suggest('mercadona', 'LECHE'));
        self::assertCount(2, ($this->handler())(new SuggestProductsQuery('mercadona', 'LECHE', 2)));
    }

    /**
     * @return list<string>
     */
    private function suggest(string $supermarketId, string $text): array
    {
        return array_map(static fn (ProductSuggestion $suggestion): string => $suggestion->productId, ($this->handler())(new SuggestProductsQuery($supermarketId, $text)));
    }

    private function handler(): SuggestProductsHandler
    {
        return new SuggestProductsHandler($this->candidates);
    }

    private function add(string $id, string $supermarketId, string $name, ?string $brand, bool $available = true): void
    {
        $this->candidates->add($supermarketId, new SuggestionCandidate($id, $name, $brand, $available));
    }
}
