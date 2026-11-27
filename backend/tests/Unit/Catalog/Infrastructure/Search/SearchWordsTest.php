<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Search;

use App\Catalog\Infrastructure\Search\SearchWords;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchWordsTest extends TestCase
{
    #[Test]
    public function it_splits_a_text_into_lowercase_words_without_accents(): void
    {
        self::assertSame(['platano', 'de', 'canarias'], SearchWords::of('Plátano de CANARIAS'));
        self::assertSame(['pinya', 'pages', 'anis', 'ou'], SearchWords::of('Pinya pagès anís ou'));
        self::assertSame(['xocolata', 'nata', 'cafe', 'pina', 'calcot'], SearchWords::of('Xocolata nata café piña calçot'));
    }

    #[Test]
    public function it_treats_anything_that_is_not_a_letter_or_a_digit_as_a_separator(): void
    {
        self::assertSame(['leche', 'entera'], SearchWords::of('"+leche* (entera)~ <@>'));
        self::assertSame(['l', 'oreal', '6x1'], SearchWords::of("L'Oréal 6x1,l"), 'each word once');
        self::assertSame([], SearchWords::of(' +-* '));
    }

    #[Test]
    public function it_keeps_a_catalan_geminated_l_as_one_word(): void
    {
        self::assertSame(['colliflor', 'cella'], SearchWords::of('Col·liflor cel.la'));
    }

    #[Test]
    public function it_lists_every_prefix_of_every_word_once(): void
    {
        self::assertSame(['l', 'le', 'lec', 'lech', 'leche', 'e', 'en'], SearchWords::prefixesOf('Leche en leche'));
    }
}
