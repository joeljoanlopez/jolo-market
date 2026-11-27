<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Application;

use App\Receipts\Application\ReadReceipt\ReadLineData;
use App\Receipts\Application\ReadReceipt\ReadReceiptCommand;
use App\Receipts\Application\ReadReceipt\ReadReceiptData;
use App\Receipts\Application\ReadReceipt\ReadReceiptHandler;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\QuantityUnit;
use App\Receipts\Domain\ValueObject\ReadBy;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use App\Receipts\Domain\ValueObject\SupermarketId;
use PHPUnit\Framework\Attributes\Test;

final class ReadReceiptHandlerTest extends ReceiptHandlersTestCase
{
    #[Test]
    public function the_worker_reads_the_photo_into_a_draft(): void
    {
        $receiptId = $this->upload();
        $this->reader->willRead(new ReadReceiptData(ReadBy::AI, '2026-11-14', 1234, [
            new ReadLineData('product', 'PLATANO', 'kg', 532, 120),
            new ReadLineData('discount', 'DTO. 2A UNIDAD', null, null, 50),
        ]));

        $this->read($receiptId);

        self::assertSame([[self::JPEG, PhotoType::JPEG, 'mercadona']], $this->reader->reads());
        $receipt = $this->receipt($receiptId);
        self::assertSame(ReceiptStatus::DRAFT, $receipt->status());
        self::assertSame(ReadBy::AI, $receipt->readBy());
        self::assertSame('2026-11-14', $receipt->date()?->value());
        self::assertSame(1234, $receipt->total()?->cents());
        [$banana, $discount] = $receipt->lines();
        self::assertSame('PLATANO', $banana->text()->value());
        self::assertSame(QuantityUnit::KG, $banana->quantity()?->unit());
        self::assertSame(532, $banana->quantity()->value());
        self::assertSame(120, $banana->amount()->cents());
        self::assertFalse($discount->isProduct());
        self::assertSame(50, $discount->amount()->cents());
    }

    #[Test]
    public function a_line_the_memory_knows_is_linked_and_any_other_gets_a_suggestion(): void
    {
        $this->memory->remember(SupermarketId::fromString('mercadona'), LineText::fromString('pan  molde'), ProductId::fromString(self::BREAD));
        $receiptId = $this->upload();
        $this->reader->willRead(new ReadReceiptData(ReadBy::OCR, null, null, [
            new ReadLineData('product', 'PAN MOLDE', 'units', 1, 135),
            new ReadLineData('product', 'LECHE ENT.', 'units', 6, 534),
            new ReadLineData('product', 'DETERGENTE', 'units', 1, 399),
        ]));

        $this->read($receiptId);

        [$bread, $milk, $detergent] = $this->receipt($receiptId)->lines();
        self::assertSame(self::BREAD, $bread->linkedProductId()?->value());
        self::assertNull($bread->suggestedProductId());
        self::assertNull($milk->linkedProductId());
        self::assertSame(self::MILK, $milk->suggestedProductId()?->value());
        self::assertNull($detergent->linkedProductId());
        self::assertNull($detergent->suggestedProductId());
    }

    #[Test]
    public function the_memory_and_the_suggestions_are_of_the_receipts_supermarket_only(): void
    {
        $this->memory->remember(SupermarketId::fromString('mercadona'), LineText::fromString('LECHE'), ProductId::fromString(self::MILK));
        $receiptId = $this->upload(supermarket: 'aldi');
        $this->reader->willRead(new ReadReceiptData(ReadBy::OCR, null, null, [new ReadLineData('product', 'LECHE', 'units', 1, 99)]));

        $this->read($receiptId);

        $line = $this->receipt($receiptId)->lines()[0];
        self::assertNull($line->linkedProductId());
        self::assertSame(self::ALDI_MILK, $line->suggestedProductId()?->value());
    }

    #[Test]
    public function a_reader_failure_gives_an_empty_draft(): void
    {
        $receiptId = $this->upload();
        $this->reader->willRead(null);

        $this->read($receiptId);

        $receipt = $this->receipt($receiptId);
        self::assertSame(ReceiptStatus::DRAFT, $receipt->status());
        self::assertSame(ReadBy::NONE, $receipt->readBy());
        self::assertSame([], $receipt->lines());
    }

    #[Test]
    public function a_missing_photo_gives_an_empty_draft(): void
    {
        $receiptId = $this->upload();
        $this->photos->delete(ReceiptId::fromString($receiptId));

        $this->read($receiptId);

        self::assertSame(ReadBy::NONE, $this->receipt($receiptId)->readBy());
        self::assertSame([], $this->reader->reads());
    }

    #[Test]
    public function parts_that_break_the_receipt_rules_are_left_out(): void
    {
        $receiptId = $this->upload();
        $this->reader->willRead(new ReadReceiptData(ReadBy::OCR, '2026-02-30', 0, [
            new ReadLineData('product', '', 'units', 1, 100),
            new ReadLineData('product', 'PAN', 'units', 0, 100),
            new ReadLineData('product', 'PAN', 'units', 1, 0),
            new ReadLineData('product', 'PAN', null, null, 100),
            new ReadLineData('product', 'LECHE', 'units', 2, 200),
        ]));

        $this->read($receiptId);

        $receipt = $this->receipt($receiptId);
        self::assertSame(ReadBy::OCR, $receipt->readBy());
        self::assertNull($receipt->date());
        self::assertNull($receipt->total());
        self::assertCount(1, $receipt->lines());
        self::assertSame('LECHE', $receipt->lines()[0]->text()->value());
    }

    #[Test]
    public function only_the_first_200_lines_are_kept(): void
    {
        $receiptId = $this->upload();
        $this->reader->willRead(new ReadReceiptData(ReadBy::AI, null, null, array_fill(0, 205, new ReadLineData('discount', 'DTO', null, null, 1))));

        $this->read($receiptId);

        self::assertCount(200, $this->receipt($receiptId)->lines());
    }

    #[Test]
    public function a_receipt_deleted_before_it_is_read_is_skipped(): void
    {
        $receiptId = $this->upload();
        $this->receipts->remove(ReceiptId::fromString($receiptId));

        $this->read($receiptId);

        self::assertSame(0, $this->receipts->count());
        self::assertSame([], $this->reader->reads());
    }

    #[Test]
    public function a_receipt_deleted_while_it_is_read_is_not_brought_back(): void
    {
        $receiptId = $this->upload();
        $this->receipts->beforeNextSave(static fn ($receipts) => $receipts->remove(ReceiptId::fromString($receiptId)));

        $this->read($receiptId);

        self::assertSame(0, $this->receipts->count());
    }

    #[Test]
    public function a_receipt_that_was_already_read_is_left_as_it_is(): void
    {
        $receiptId = $this->upload();
        $this->read($receiptId);
        $this->reader->willRead(new ReadReceiptData(ReadBy::AI, '2026-11-14', 100, []));

        $this->read($receiptId);

        self::assertSame(ReadBy::NONE, $this->receipt($receiptId)->readBy());
        self::assertCount(1, $this->reader->reads());
    }

    private function read(string $receiptId): void
    {
        (new ReadReceiptHandler($this->receipts, $this->photos, $this->reader, $this->lineLinker(), $this->ids, $this->clock))(new ReadReceiptCommand($receiptId));
    }
}
