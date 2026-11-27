<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\ValueObject;

use App\Receipts\Domain\ValueObject\InvalidLineText;
use App\Receipts\Domain\ValueObject\LineText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LineTextTest extends TestCase
{
    #[Test]
    public function it_keeps_the_text_as_printed_without_surrounding_spaces(): void
    {
        self::assertSame('LECHE ENT.  HACEND', LineText::fromString('  LECHE ENT.  HACEND ')->value());
    }

    #[Test]
    public function it_accepts_120_characters(): void
    {
        self::assertSame(120, mb_strlen(LineText::fromString(str_repeat('ñ', 120))->value()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield '121 characters' => [str_repeat('a', 121)];
    }

    #[Test]
    #[DataProvider('invalidTexts')]
    public function it_rejects_a_text_out_of_1_to_120_characters(string $text): void
    {
        $this->expectException(InvalidLineText::class);

        LineText::fromString($text);
    }

    #[Test]
    public function its_normalized_form_ignores_case_and_repeated_spaces(): void
    {
        self::assertSame('leche ent. hacend', LineText::fromString("Leche  ENT.\t hacend")->normalized());
        self::assertSame('pàtata ñ', LineText::fromString('PÀTATA Ñ')->normalized());
    }
}
