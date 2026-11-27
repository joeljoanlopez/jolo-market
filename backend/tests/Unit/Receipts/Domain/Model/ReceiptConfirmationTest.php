<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\Model;

use App\Receipts\Domain\Model\ReadResult;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Model\ReceiptNotConfirmable;
use App\Receipts\Domain\Model\ReceiptStillReading;
use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ReceiptConfirmationTest extends ReceiptTestCase
{
    private const string NOW = '2026-11-14 19:00:00';

    #[Test]
    public function a_draft_with_a_date_and_a_total_is_confirmed(): void
    {
        $receipt = self::draft();
        $receipt->changeDate(ReceiptDate::fromString('2026-11-14'), self::at(self::NOW));
        $receipt->changeTotal(Money::eur(1000));

        $receipt->confirm();

        self::assertSame(ReceiptStatus::CONFIRMED, $receipt->status());
    }

    #[Test]
    public function a_receipt_with_a_difference_can_still_be_confirmed(): void
    {
        $receipt = self::draft();
        $receipt->addDiscountLine(self::lineId(1), LineText::fromString('DTO'), Money::eur(10));
        $receipt->changeDate(ReceiptDate::fromString('2026-11-14'), self::at(self::NOW));
        $receipt->changeTotal(Money::eur(1000));

        $receipt->confirm();

        self::assertSame(ReceiptStatus::CONFIRMED, $receipt->status());
    }

    #[Test]
    public function a_receipt_without_a_date_is_not_confirmable(): void
    {
        $receipt = self::draft();
        $receipt->changeTotal(Money::eur(1000));

        $this->expectException(ReceiptNotConfirmable::class);

        $receipt->confirm();
    }

    #[Test]
    public function a_receipt_without_a_total_is_not_confirmable(): void
    {
        $receipt = self::draft();
        $receipt->changeDate(ReceiptDate::fromString('2026-11-14'), self::at(self::NOW));

        $this->expectException(ReceiptNotConfirmable::class);

        $receipt->confirm();
    }

    #[Test]
    public function editing_a_confirmed_receipt_keeps_it_confirmed_and_it_can_be_confirmed_again(): void
    {
        $receipt = self::confirmed();

        $receipt->addProductLine(self::lineId(1), LineText::fromString('PAN'), LineQuantity::units(1), Money::eur(100));
        $receipt->linkProduct(self::lineId(1), ProductId::fromString(self::BREAD));
        self::assertSame(ReceiptStatus::CONFIRMED, $receipt->status());

        $receipt->confirm();
        self::assertSame(ReceiptStatus::CONFIRMED, $receipt->status());
    }

    /**
     * @return iterable<string, array{callable(Receipt): void}>
     */
    public static function edits(): iterable
    {
        yield 'confirm' => [static fn (Receipt $receipt) => $receipt->confirm()];
        yield 'change the date' => [static fn (Receipt $receipt) => $receipt->changeDate(ReceiptDate::fromString('2026-11-14'), self::at(self::NOW))];
        yield 'change the total' => [static fn (Receipt $receipt) => $receipt->changeTotal(Money::eur(1))];
        yield 'add a product line' => [static fn (Receipt $receipt) => $receipt->addProductLine(self::lineId(1), LineText::fromString('PAN'), LineQuantity::units(1), Money::eur(1))];
        yield 'add a discount line' => [static fn (Receipt $receipt) => $receipt->addDiscountLine(self::lineId(1), LineText::fromString('DTO'), Money::eur(1))];
        yield 'update a line' => [static fn (Receipt $receipt) => $receipt->updateLine(self::lineId(1), LineText::fromString('PAN'), LineQuantity::units(1), Money::eur(1))];
        yield 'remove a line' => [static fn (Receipt $receipt) => $receipt->removeLine(self::lineId(1))];
        yield 'link a line' => [static fn (Receipt $receipt) => $receipt->linkProduct(self::lineId(1), ProductId::fromString(self::BREAD))];
        yield 'suggest a product' => [static fn (Receipt $receipt) => $receipt->suggestProduct(self::lineId(1), ProductId::fromString(self::BREAD))];
        yield 'unlink a line' => [static fn (Receipt $receipt) => $receipt->unlink(self::lineId(1))];
    }

    /**
     * @param callable(Receipt): void $edit
     */
    #[Test]
    #[DataProvider('edits')]
    public function a_receipt_still_being_read_cannot_be_edited_or_confirmed(callable $edit): void
    {
        $this->expectException(ReceiptStillReading::class);

        $edit(self::uploaded());
    }

    private static function draft(): Receipt
    {
        $receipt = self::uploaded();
        $receipt->completeReading(ReadResult::failed(), self::at(self::NOW));

        return $receipt;
    }

    private static function confirmed(): Receipt
    {
        $receipt = self::draft();
        $receipt->changeDate(ReceiptDate::fromString('2026-11-14'), self::at(self::NOW));
        $receipt->changeTotal(Money::eur(1000));
        $receipt->confirm();

        return $receipt;
    }
}
