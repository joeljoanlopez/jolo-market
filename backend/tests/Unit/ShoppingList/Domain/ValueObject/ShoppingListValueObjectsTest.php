<?php

declare(strict_types=1);

namespace App\Tests\Unit\ShoppingList\Domain\ValueObject;

use App\ShoppingList\Domain\ValueObject\InvalidItemText;
use App\ShoppingList\Domain\ValueObject\InvalidListName;
use App\ShoppingList\Domain\ValueObject\InvalidQuantity;
use App\ShoppingList\Domain\ValueObject\InvalidShareToken;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\Quantity;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShoppingListValueObjectsTest extends TestCase
{
    #[Test]
    public function a_list_name_is_trimmed(): void
    {
        self::assertSame('Compra semanal', ListName::fromString('  Compra semanal ')->value());
    }

    #[Test]
    public function a_list_name_counts_characters_not_bytes(): void
    {
        self::assertSame(str_repeat('ñ', 80), ListName::fromString(str_repeat('ñ', 80))->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidListNames(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'too long' => [str_repeat('a', 81)];
    }

    #[Test]
    #[DataProvider('invalidListNames')]
    public function a_list_name_needs_one_to_eighty_characters(string $name): void
    {
        $this->expectException(InvalidListName::class);

        ListName::fromString($name);
    }

    #[Test]
    public function an_item_text_is_trimmed_and_accepts_up_to_120_characters(): void
    {
        self::assertSame('pan', ItemText::fromString(' pan ')->value());
        self::assertSame(str_repeat('é', 120), ItemText::fromString(str_repeat('é', 120))->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidItemTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ["  \t "];
        yield 'too long' => [str_repeat('a', 121)];
    }

    #[Test]
    #[DataProvider('invalidItemTexts')]
    public function an_item_text_needs_one_to_120_characters(string $text): void
    {
        $this->expectException(InvalidItemText::class);

        ItemText::fromString($text);
    }

    #[Test]
    public function a_quantity_is_a_whole_number_from_1_to_99(): void
    {
        self::assertSame(1, Quantity::of(1)->value());
        self::assertSame(99, Quantity::of(99)->value());
        self::assertSame(1, Quantity::default()->value());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidQuantities(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above 99' => [100];
    }

    #[Test]
    #[DataProvider('invalidQuantities')]
    public function a_quantity_outside_1_to_99_is_rejected(int $quantity): void
    {
        $this->expectException(InvalidQuantity::class);

        Quantity::of($quantity);
    }

    #[Test]
    public function quantities_add_up_while_they_stay_within_99(): void
    {
        self::assertSame(99, Quantity::of(90)->add(Quantity::of(9))->value());

        $this->expectException(InvalidQuantity::class);
        Quantity::of(90)->add(Quantity::of(10));
    }

    #[Test]
    public function a_share_token_is_url_safe_and_long_enough_to_hold_128_random_bits(): void
    {
        $token = ShareToken::fromString('kq3-Zx9_vQm3kT8wLr2Nf6');

        self::assertSame('kq3-Zx9_vQm3kT8wLr2Nf6', $token->value());
        self::assertTrue($token->equals(ShareToken::fromString('kq3-Zx9_vQm3kT8wLr2Nf6')));
        self::assertFalse($token->equals(ShareToken::fromString('kq3-Zx9_vQm3kT8wLr2Nf7')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidShareTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'shorter than 22 characters' => [str_repeat('a', 21)];
        yield 'longer than 64 characters' => [str_repeat('a', 65)];
        yield 'not URL-safe' => ['kq3+Zx9/vQm3kT8wLr2Nf6'];
        yield 'padding' => ['kq3-Zx9_vQm3kT8wLr2Nf6=='];
    }

    #[Test]
    #[DataProvider('invalidShareTokens')]
    public function a_malformed_share_token_is_rejected(string $token): void
    {
        $this->expectException(InvalidShareToken::class);

        ShareToken::fromString($token);
    }
}
