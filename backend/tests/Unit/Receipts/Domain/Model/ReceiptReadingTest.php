<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\Model;

use App\Receipts\Domain\Model\InvalidReceiptAmount;
use App\Receipts\Domain\Model\ReadResult;
use App\Receipts\Domain\Model\ReceiptAlreadyRead;
use App\Receipts\Domain\Model\TooManyReceiptLines;
use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\QuantityUnit;
use App\Receipts\Domain\ValueObject\ReadBy;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;

final class ReceiptReadingTest extends ReceiptTestCase
{
    #[Test]
    public function an_uploaded_receipt_is_being_read(): void
    {
        $receipt = self::uploaded();

        self::assertSame(self::RECEIPT, $receipt->id()->value());
        self::assertSame(self::LIST, $receipt->listId()->value());
        self::assertSame('mercadona', $receipt->supermarketId()->value());
        self::assertSame(self::MARIA, $receipt->uploaderId()->value());
        self::assertSame(PhotoType::JPEG, $receipt->photoType());
        self::assertEquals(self::at('2026-11-14 18:00:00'), $receipt->uploadedAt());
        self::assertSame(ReceiptStatus::READING, $receipt->status());
        self::assertNull($receipt->readBy());
        self::assertNull($receipt->date());
        self::assertNull($receipt->total());
        self::assertSame([], $receipt->lines());
    }

    #[Test]
    public function completing_the_reading_makes_a_draft_with_what_was_read(): void
    {
        $receipt = self::uploaded();

        $receipt->completeReading(ReadResult::read(
            ReadBy::AI,
            ReceiptDate::fromString('2026-11-14'),
            Money::eur(1234),
            [self::productLine(1, 'LECHE ENTERA', 534, LineQuantity::units(6)), self::productLine(2, 'PLATANO', 120, LineQuantity::kilograms(532)), self::discountLine(3, 'DTO. LECHE', 50)],
        ), self::at('2026-11-14 18:01:00'));

        self::assertSame(ReceiptStatus::DRAFT, $receipt->status());
        self::assertSame(ReadBy::AI, $receipt->readBy());
        self::assertSame('2026-11-14', $receipt->date()?->value());
        self::assertSame(1234, $receipt->total()?->cents());
        self::assertCount(3, $receipt->lines());
        [$milk, $banana, $discount] = $receipt->lines();
        self::assertTrue($milk->isProduct());
        self::assertSame('LECHE ENTERA', $milk->text()->value());
        self::assertSame(6, $milk->quantity()?->value());
        self::assertSame(534, $milk->amount()->cents());
        self::assertSame(QuantityUnit::KG, $banana->quantity()?->unit());
        self::assertFalse($discount->isProduct());
        self::assertNull($discount->quantity());
    }

    #[Test]
    public function a_failed_reading_makes_an_empty_draft_read_by_none(): void
    {
        $receipt = self::uploaded();

        $receipt->completeReading(ReadResult::failed(), self::at('2026-11-14 18:01:00'));

        self::assertSame(ReceiptStatus::DRAFT, $receipt->status());
        self::assertSame(ReadBy::NONE, $receipt->readBy());
        self::assertNull($receipt->date());
        self::assertNull($receipt->total());
        self::assertSame([], $receipt->lines());
    }

    #[Test]
    public function a_date_read_in_the_future_is_left_out(): void
    {
        $receipt = self::uploaded();

        $receipt->completeReading(ReadResult::read(ReadBy::OCR, ReceiptDate::fromString('2026-11-16'), null, []), self::at('2026-11-14 18:01:00'));

        self::assertNull($receipt->date());
        self::assertSame(ReadBy::OCR, $receipt->readBy());
    }

    #[Test]
    public function a_receipt_is_read_only_once(): void
    {
        $receipt = self::uploaded();
        $receipt->completeReading(ReadResult::failed(), self::at('2026-11-14 18:01:00'));

        $this->expectException(ReceiptAlreadyRead::class);

        $receipt->completeReading(ReadResult::failed(), self::at('2026-11-14 18:02:00'));
    }

    #[Test]
    public function a_successful_reading_is_by_the_ai_or_the_ocr(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReadResult::read(ReadBy::NONE, null, null, []);
    }

    #[Test]
    public function a_reading_has_at_most_200_lines(): void
    {
        $lines = [];
        for ($n = 1; $n <= 201; ++$n) {
            $lines[] = self::productLine($n, 'PAN', 100);
        }

        $this->expectException(TooManyReceiptLines::class);

        ReadResult::read(ReadBy::OCR, null, null, $lines);
    }

    #[Test]
    public function a_read_total_is_more_than_zero(): void
    {
        $this->expectException(InvalidReceiptAmount::class);

        ReadResult::read(ReadBy::OCR, null, Money::eur(0), []);
    }

    #[Test]
    public function every_line_amount_is_more_than_zero(): void
    {
        $this->expectException(InvalidReceiptAmount::class);

        self::productLine(1, 'PAN', 0);
    }

    #[Test]
    public function a_photo_is_a_jpeg_png_or_webp_image(): void
    {
        self::assertSame(PhotoType::PNG, PhotoType::fromMediaType('image/png'));
        self::assertSame('image/webp', PhotoType::WEBP->mediaType());
        self::assertNull(PhotoType::fromMediaType('application/pdf'));
    }
}
