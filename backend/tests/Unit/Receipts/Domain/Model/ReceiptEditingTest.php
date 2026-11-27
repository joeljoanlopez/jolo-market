<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\Model;

use App\Receipts\Domain\Model\InvalidReceiptAmount;
use App\Receipts\Domain\Model\NotAProductLine;
use App\Receipts\Domain\Model\ReadResult;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Model\ReceiptLineNotFound;
use App\Receipts\Domain\Model\TooManyReceiptLines;
use App\Receipts\Domain\ValueObject\InvalidReceiptDate;
use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\ReadBy;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;

final class ReceiptEditingTest extends ReceiptTestCase
{
    private const string NOW = '2026-11-14 19:00:00';

    #[Test]
    public function a_member_adds_product_and_discount_lines_at_the_end(): void
    {
        $receipt = self::draft();

        $receipt->addProductLine(self::lineId(1), LineText::fromString('PAN'), LineQuantity::units(2), Money::eur(180));
        $receipt->addDiscountLine(self::lineId(2), LineText::fromString('DTO'), Money::eur(30));

        [$bread, $discount] = $receipt->lines();
        self::assertTrue($bread->isProduct());
        self::assertSame(2, $bread->quantity()?->value());
        self::assertFalse($discount->isProduct());
        self::assertSame(30, $discount->amount()->cents());
    }

    #[Test]
    public function a_receipt_has_at_most_200_lines(): void
    {
        $receipt = self::draft();
        for ($n = 1; $n <= Receipt::MAX_LINES; ++$n) {
            $receipt->addDiscountLine(self::lineId($n), LineText::fromString('DTO'), Money::eur(1));
        }

        $this->expectException(TooManyReceiptLines::class);

        $receipt->addProductLine(self::lineId(201), LineText::fromString('PAN'), LineQuantity::units(1), Money::eur(1));
    }

    #[Test]
    public function a_member_updates_a_product_line(): void
    {
        $receipt = self::draft(self::productLine(1, 'PAN', 100));

        $receipt->updateLine(self::lineId(1), LineText::fromString('PAN BARRA'), LineQuantity::kilograms(250), Money::eur(90));

        $line = $receipt->lines()[0];
        self::assertSame('PAN BARRA', $line->text()->value());
        self::assertTrue(LineQuantity::kilograms(250)->equals($line->quantity() ?? self::fail()));
        self::assertSame(90, $line->amount()->cents());
    }

    #[Test]
    public function a_product_line_keeps_a_quantity(): void
    {
        $receipt = self::draft(self::productLine(1, 'PAN', 100));

        $this->expectException(\InvalidArgumentException::class);

        $receipt->updateLine(self::lineId(1), LineText::fromString('PAN'), null, Money::eur(90));
    }

    #[Test]
    public function a_discount_line_has_no_quantity(): void
    {
        $receipt = self::draft(self::discountLine(1, 'DTO', 10));
        $receipt->updateLine(self::lineId(1), LineText::fromString('DESCUENTO'), null, Money::eur(20));

        self::assertSame('DESCUENTO', $receipt->lines()[0]->text()->value());

        $this->expectException(NotAProductLine::class);
        $receipt->updateLine(self::lineId(1), LineText::fromString('DTO'), LineQuantity::units(1), Money::eur(20));
    }

    #[Test]
    public function changing_the_text_drops_the_link_and_the_suggestion(): void
    {
        $receipt = self::draft(self::productLine(1, 'PAN', 100), self::productLine(2, 'LECHE', 100));
        $receipt->linkProduct(self::lineId(1), ProductId::fromString(self::BREAD));
        $receipt->suggestProduct(self::lineId(2), ProductId::fromString(self::MILK));

        $receipt->updateLine(self::lineId(1), LineText::fromString('PAN BARRA'), LineQuantity::units(1), Money::eur(100));
        $receipt->updateLine(self::lineId(2), LineText::fromString('LECHE ENT'), LineQuantity::units(1), Money::eur(100));

        self::assertNull($receipt->lines()[0]->linkedProductId());
        self::assertNull($receipt->lines()[1]->suggestedProductId());
    }

    #[Test]
    public function changing_only_the_quantity_or_amount_keeps_the_link(): void
    {
        $receipt = self::draft(self::productLine(1, 'PAN', 100));
        $receipt->linkProduct(self::lineId(1), ProductId::fromString(self::BREAD));

        $receipt->updateLine(self::lineId(1), LineText::fromString(' PAN '), LineQuantity::units(3), Money::eur(300));

        self::assertSame(self::BREAD, $receipt->lines()[0]->linkedProductId()?->value());
    }

    #[Test]
    public function a_member_removes_a_line(): void
    {
        $receipt = self::draft(self::productLine(1, 'PAN', 100), self::discountLine(2, 'DTO', 10));

        $receipt->removeLine(self::lineId(1));

        self::assertCount(1, $receipt->lines());
        self::assertSame('DTO', $receipt->lines()[0]->text()->value());
    }

    #[Test]
    public function an_unknown_line_is_not_found(): void
    {
        $receipt = self::draft();

        $this->expectException(ReceiptLineNotFound::class);

        $receipt->removeLine(self::lineId(9));
    }

    #[Test]
    public function linking_a_product_replaces_the_suggestion(): void
    {
        $receipt = self::draft(self::productLine(1, 'LECHE', 100));
        $receipt->suggestProduct(self::lineId(1), ProductId::fromString(self::MILK));

        $receipt->linkProduct(self::lineId(1), ProductId::fromString(self::MILK));

        self::assertSame(self::MILK, $receipt->lines()[0]->linkedProductId()?->value());
        self::assertNull($receipt->lines()[0]->suggestedProductId());
    }

    #[Test]
    public function unlinking_leaves_the_line_without_a_product(): void
    {
        $receipt = self::draft(self::productLine(1, 'LECHE', 100));
        $receipt->linkProduct(self::lineId(1), ProductId::fromString(self::MILK));

        $receipt->unlink(self::lineId(1));

        self::assertNull($receipt->lines()[0]->linkedProductId());
        self::assertNull($receipt->lines()[0]->suggestedProductId());
    }

    #[Test]
    public function a_discount_line_is_never_linked(): void
    {
        $receipt = self::draft(self::discountLine(1, 'DTO', 10));

        $this->expectException(NotAProductLine::class);

        $receipt->linkProduct(self::lineId(1), ProductId::fromString(self::MILK));
    }

    #[Test]
    public function a_discount_line_gets_no_suggestion(): void
    {
        $receipt = self::draft(self::discountLine(1, 'DTO', 10));

        $this->expectException(NotAProductLine::class);

        $receipt->suggestProduct(self::lineId(1), ProductId::fromString(self::MILK));
    }

    #[Test]
    public function a_member_changes_the_date_and_the_total(): void
    {
        $receipt = self::draft();

        $receipt->changeDate(ReceiptDate::fromString('2026-11-14'), self::at(self::NOW));
        $receipt->changeTotal(Money::eur(2500));

        self::assertSame('2026-11-14', $receipt->date()?->value());
        self::assertSame(2500, $receipt->total()?->cents());
    }

    #[Test]
    public function the_date_is_never_in_the_future_in_madrid(): void
    {
        $receipt = self::draft();
        // 23:30 UTC is already 15 November in Madrid.
        $receipt->changeDate(ReceiptDate::fromString('2026-11-15'), self::at('2026-11-14 23:30:00'));

        $this->expectException(InvalidReceiptDate::class);

        $receipt->changeDate(ReceiptDate::fromString('2026-11-16'), self::at('2026-11-14 23:30:00'));
    }

    #[Test]
    public function the_total_is_more_than_zero(): void
    {
        $this->expectException(InvalidReceiptAmount::class);

        self::draft()->changeTotal(Money::eur(0));
    }

    #[Test]
    public function the_difference_is_products_minus_discounts_minus_the_total(): void
    {
        $receipt = self::draft(self::productLine(1, 'PAN', 300), self::productLine(2, 'LECHE', 200), self::discountLine(3, 'DTO', 50));

        self::assertNull($receipt->difference());

        $receipt->changeTotal(Money::eur(450));
        self::assertSame(0, $receipt->difference()?->sign);
        self::assertSame(0, $receipt->difference()->amount->cents());

        $receipt->changeTotal(Money::eur(500));
        self::assertSame(-1, $receipt->difference()?->sign);
        self::assertSame(50, $receipt->difference()->amount->cents());

        $receipt->changeTotal(Money::eur(400));
        self::assertSame(1, $receipt->difference()?->sign);
        self::assertSame(50, $receipt->difference()->amount->cents());
        self::assertSame(50, $receipt->difference()->signedCents());
    }

    #[Test]
    public function every_edit_moves_the_version(): void
    {
        $receipt = self::draft();
        $before = $receipt->version();

        $receipt->addDiscountLine(self::lineId(1), LineText::fromString('DTO'), Money::eur(1));
        $receipt->changeTotal(Money::eur(10));

        self::assertSame($before + 2, $receipt->version());
    }

    private static function draft(\App\Receipts\Domain\Model\ReceiptLine ...$lines): Receipt
    {
        $receipt = self::uploaded();
        $receipt->completeReading(ReadResult::read(ReadBy::OCR, null, null, array_values($lines)), self::at(self::NOW));

        return $receipt;
    }
}
