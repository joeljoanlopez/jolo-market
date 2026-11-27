<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Application;

use App\Receipts\Application\EditReceipt\AddLineCommand;
use App\Receipts\Application\EditReceipt\AddLineHandler;
use App\Receipts\Application\EditReceipt\DeleteReceiptCommand;
use App\Receipts\Application\EditReceipt\DeleteReceiptHandler;
use App\Receipts\Application\EditReceipt\InvalidLine;
use App\Receipts\Application\EditReceipt\LinkLineCommand;
use App\Receipts\Application\EditReceipt\LinkLineHandler;
use App\Receipts\Application\EditReceipt\ProductNotOfSupermarket;
use App\Receipts\Application\EditReceipt\RemoveLineCommand;
use App\Receipts\Application\EditReceipt\RemoveLineHandler;
use App\Receipts\Application\EditReceipt\UnlinkLineCommand;
use App\Receipts\Application\EditReceipt\UnlinkLineHandler;
use App\Receipts\Application\EditReceipt\UpdateLineCommand;
use App\Receipts\Application\EditReceipt\UpdateLineHandler;
use App\Receipts\Application\EditReceipt\UpdateReceiptCommand;
use App\Receipts\Application\EditReceipt\UpdateReceiptHandler;
use App\Receipts\Application\ReceiptListNotFound;
use App\Receipts\Application\ReceiptNotFound;
use App\Receipts\Domain\Model\InvalidReceiptAmount;
use App\Receipts\Domain\Model\ReceiptLineNotFound;
use App\Receipts\Domain\Model\ReceiptStillReading;
use App\Receipts\Domain\ValueObject\InvalidLineQuantity;
use App\Receipts\Domain\ValueObject\InvalidLineText;
use App\Receipts\Domain\ValueObject\InvalidReceiptDate;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\QuantityUnit;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\SupermarketId;
use PHPUnit\Framework\Attributes\Test;

final class EditReceiptHandlersTest extends ReceiptHandlersTestCase
{
    #[Test]
    public function a_member_changes_the_date_and_the_total(): void
    {
        $receiptId = $this->draft();

        $this->updateReceipt(self::JOAN, $receiptId, '2026-11-14', 2599);

        $receipt = $this->receipt($receiptId);
        self::assertSame('2026-11-14', $receipt->date()?->value());
        self::assertSame(2599, $receipt->total()?->cents());
    }

    #[Test]
    public function a_field_that_is_not_sent_is_kept(): void
    {
        $receiptId = $this->draft();
        $this->updateReceipt(self::MARIA, $receiptId, '2026-11-13', 100);

        $this->updateReceipt(self::MARIA, $receiptId, null, 200);
        $this->updateReceipt(self::MARIA, $receiptId, '2026-11-14', null);

        $receipt = $this->receipt($receiptId);
        self::assertSame('2026-11-14', $receipt->date()?->value());
        self::assertSame(200, $receipt->total()?->cents());
    }

    #[Test]
    public function the_date_is_a_real_day_not_in_the_future_and_the_total_is_positive(): void
    {
        $receiptId = $this->draft();

        $this->expectExceptionIn(InvalidReceiptDate::class, fn () => $this->updateReceipt(self::MARIA, $receiptId, '2026-11-15', null));
        $this->expectExceptionIn(InvalidReceiptDate::class, fn () => $this->updateReceipt(self::MARIA, $receiptId, '14/11/2026', null));
        $this->expectExceptionIn(InvalidReceiptAmount::class, fn () => $this->updateReceipt(self::MARIA, $receiptId, null, 0));
        $this->expectExceptionIn(InvalidReceiptAmount::class, fn () => $this->updateReceipt(self::MARIA, $receiptId, null, -5));
    }

    #[Test]
    public function a_member_adds_a_product_line_that_the_memory_links(): void
    {
        $this->memory->remember(SupermarketId::fromString('mercadona'), LineText::fromString('PAN MOLDE'), ProductId::fromString(self::BREAD));
        $receiptId = $this->draft();

        $lineId = $this->addLine($receiptId, 'product', 'pan  molde', 'units', 2, 270);

        $line = $this->receipt($receiptId)->lines()[0];
        self::assertSame($lineId, $line->id()->value());
        self::assertSame('pan  molde', $line->text()->value());
        self::assertSame(2, $line->quantity()?->value());
        self::assertSame(270, $line->amount()->cents());
        self::assertSame(self::BREAD, $line->linkedProductId()?->value());
    }

    #[Test]
    public function a_hand_made_line_the_memory_does_not_know_gets_a_suggestion(): void
    {
        $receiptId = $this->draft();

        $this->addLine($receiptId, 'product', 'LECHE ENT.', 'kg', 1500, 300);

        $line = $this->receipt($receiptId)->lines()[0];
        self::assertSame(QuantityUnit::KG, $line->quantity()?->unit());
        self::assertNull($line->linkedProductId());
        self::assertSame(self::MILK, $line->suggestedProductId()?->value());
    }

    #[Test]
    public function a_member_adds_a_discount_line_without_a_quantity(): void
    {
        $receiptId = $this->draft();

        $this->addLine($receiptId, 'discount', 'LECHE DTO', null, null, 50);

        $line = $this->receipt($receiptId)->lines()[0];
        self::assertFalse($line->isProduct());
        self::assertNull($line->suggestedProductId());
    }

    #[Test]
    public function a_line_needs_a_known_kind_a_valid_text_quantity_and_amount(): void
    {
        $receiptId = $this->draft();

        $this->expectExceptionIn(InvalidLine::class, fn () => $this->addLine($receiptId, 'tax', 'IVA', null, null, 10));
        $this->expectExceptionIn(InvalidLine::class, fn () => $this->addLine($receiptId, 'product', 'PAN', 'litres', 1, 10));
        $this->expectExceptionIn(InvalidLine::class, fn () => $this->addLine($receiptId, 'product', 'PAN', null, null, 10));
        $this->expectExceptionIn(InvalidLine::class, fn () => $this->addLine($receiptId, 'discount', 'DTO', 'units', 1, 10));
        $this->expectExceptionIn(InvalidLineText::class, fn () => $this->addLine($receiptId, 'product', ' ', 'units', 1, 10));
        $this->expectExceptionIn(InvalidLineQuantity::class, fn () => $this->addLine($receiptId, 'product', 'PAN', 'units', 1000, 10));
        $this->expectExceptionIn(InvalidReceiptAmount::class, fn () => $this->addLine($receiptId, 'product', 'PAN', 'units', 1, 0));
        self::assertSame([], $this->receipt($receiptId)->lines());
    }

    #[Test]
    public function changing_the_text_of_a_line_links_it_again(): void
    {
        $this->memory->remember(SupermarketId::fromString('mercadona'), LineText::fromString('PAN MOLDE'), ProductId::fromString(self::BREAD));
        $receiptId = $this->draft();
        $lineId = $this->addLine($receiptId, 'product', 'LECHE', 'units', 1, 100);

        $this->updateLine($receiptId, $lineId, text: 'PAN MOLDE');

        $line = $this->receipt($receiptId)->lines()[0];
        self::assertSame(self::BREAD, $line->linkedProductId()?->value());
        self::assertNull($line->suggestedProductId());
    }

    #[Test]
    public function changing_the_quantity_or_amount_keeps_the_text_and_the_chosen_link(): void
    {
        $receiptId = $this->draft();
        $lineId = $this->addLine($receiptId, 'product', 'PAN', 'units', 1, 100);
        $this->link($receiptId, $lineId, self::BREAD);

        $this->updateLine($receiptId, $lineId, unit: 'kg', quantity: 250, amountCents: 90);

        $line = $this->receipt($receiptId)->lines()[0];
        self::assertSame('PAN', $line->text()->value());
        self::assertSame(250, $line->quantity()?->value());
        self::assertSame(90, $line->amount()->cents());
        self::assertSame(self::BREAD, $line->linkedProductId()?->value());
    }

    #[Test]
    public function a_quantity_needs_its_unit(): void
    {
        $receiptId = $this->draft();
        $lineId = $this->addLine($receiptId, 'product', 'PAN', 'units', 1, 100);

        $this->expectException(InvalidLine::class);

        $this->updateLine($receiptId, $lineId, quantity: 2);
    }

    #[Test]
    public function a_member_removes_a_line(): void
    {
        $receiptId = $this->draft();
        $lineId = $this->addLine($receiptId, 'discount', 'DTO', null, null, 10);

        (new RemoveLineHandler($this->updater()))(new RemoveLineCommand(self::JOAN, self::LIST, $receiptId, $lineId));

        self::assertSame([], $this->receipt($receiptId)->lines());
    }

    #[Test]
    public function a_member_links_a_product_of_the_receipts_supermarket_available_or_not(): void
    {
        $receiptId = $this->draft();
        $lineId = $this->addLine($receiptId, 'product', 'LECHE', 'units', 1, 100);

        $this->link($receiptId, $lineId, self::BREAD);

        $line = $this->receipt($receiptId)->lines()[0];
        self::assertSame(self::BREAD, $line->linkedProductId()?->value());
        self::assertNull($line->suggestedProductId());
    }

    #[Test]
    public function a_product_of_another_supermarket_or_unknown_cannot_be_linked(): void
    {
        $receiptId = $this->draft();
        $lineId = $this->addLine($receiptId, 'product', 'LECHE', 'units', 1, 100);

        $this->expectExceptionIn(ProductNotOfSupermarket::class, fn () => $this->link($receiptId, $lineId, self::ALDI_MILK));
        $this->expectExceptionIn(ProductNotOfSupermarket::class, fn () => $this->link($receiptId, $lineId, self::UNKNOWN));
        $this->expectExceptionIn(ProductNotOfSupermarket::class, fn () => $this->link($receiptId, $lineId, 'milk'));
    }

    #[Test]
    public function a_member_unlinks_a_line(): void
    {
        $receiptId = $this->draft();
        $lineId = $this->addLine($receiptId, 'product', 'LECHE', 'units', 1, 100);

        (new UnlinkLineHandler($this->updater()))(new UnlinkLineCommand(self::MARIA, self::LIST, $receiptId, $lineId));

        $line = $this->receipt($receiptId)->lines()[0];
        self::assertNull($line->linkedProductId());
        self::assertNull($line->suggestedProductId());
    }

    #[Test]
    public function a_member_deletes_a_receipt_with_its_photo_in_any_status(): void
    {
        $reading = $this->upload();
        $draft = $this->draft();

        $this->delete(self::JOAN, $reading);
        $this->delete(self::MARIA, $draft);

        self::assertSame(0, $this->receipts->count());
        self::assertSame(0, $this->photos->count());
    }

    #[Test]
    public function a_receipt_still_being_read_cannot_be_edited(): void
    {
        $receiptId = $this->upload();

        $this->expectException(ReceiptStillReading::class);

        $this->updateReceipt(self::MARIA, $receiptId, '2026-11-14', null);
    }

    #[Test]
    public function an_outsider_is_told_the_list_does_not_exist(): void
    {
        $receiptId = $this->draft();

        $this->expectExceptionIn(ReceiptListNotFound::class, fn () => $this->updateReceipt(self::PEDRO, $receiptId, '2026-11-14', null));
        $this->expectExceptionIn(ReceiptListNotFound::class, fn () => $this->delete(self::PEDRO, $receiptId));
        self::assertSame(1, $this->receipts->count());
    }

    #[Test]
    public function an_unknown_or_malformed_receipt_or_one_of_another_list_is_not_found(): void
    {
        $receiptId = $this->draft();

        $this->expectExceptionIn(ReceiptNotFound::class, fn () => $this->updateReceipt(self::MARIA, self::UNKNOWN, '2026-11-14', null));
        $this->expectExceptionIn(ReceiptNotFound::class, fn () => $this->updateReceipt(self::MARIA, 'receipt-1', '2026-11-14', null));
        $this->expectExceptionIn(ReceiptNotFound::class, fn () => (new UpdateReceiptHandler($this->updater(), $this->clock))(new UpdateReceiptCommand(self::PEDRO, self::OTHER_LIST, $receiptId, '2026-11-14', null)));
        $this->expectExceptionIn(ReceiptNotFound::class, fn () => (new DeleteReceiptHandler($this->updater(), $this->receipts, $this->photos))(new DeleteReceiptCommand(self::PEDRO, self::OTHER_LIST, $receiptId)));
        self::assertSame(1, $this->receipts->count());
    }

    #[Test]
    public function an_unknown_or_malformed_line_is_not_found(): void
    {
        $receiptId = $this->draft();

        $this->expectExceptionIn(ReceiptLineNotFound::class, fn () => $this->updateLine($receiptId, self::UNKNOWN, text: 'PAN'));
        $this->expectExceptionIn(ReceiptLineNotFound::class, fn () => $this->link($receiptId, 'line-1', self::MILK));
    }

    #[Test]
    public function an_edit_runs_again_when_someone_else_saved_first(): void
    {
        $receiptId = $this->draft();
        $this->receipts->beforeNextSave(function () use ($receiptId): void {
            $this->addLine($receiptId, 'discount', 'DTO', null, null, 10);
        });

        $this->addLine($receiptId, 'product', 'PAN', 'units', 1, 100);

        self::assertCount(2, $this->receipt($receiptId)->lines());
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    private function expectExceptionIn(string $exception, callable $action): void
    {
        try {
            $action();
            self::fail(\sprintf('Expected a %s.', $exception));
        } catch (\Throwable $thrown) {
            self::assertInstanceOf($exception, $thrown);
        }
    }

    private function updateReceipt(string $actor, string $receiptId, ?string $date, ?int $totalCents): void
    {
        (new UpdateReceiptHandler($this->updater(), $this->clock))(new UpdateReceiptCommand($actor, self::LIST, $receiptId, $date, $totalCents));
    }

    private function addLine(string $receiptId, string $kind, string $text, ?string $unit, ?int $quantity, int $amountCents): string
    {
        return (new AddLineHandler($this->updater(), $this->lineLinker(), $this->ids))(new AddLineCommand(self::MARIA, self::LIST, $receiptId, $kind, $text, $unit, $quantity, $amountCents));
    }

    private function updateLine(string $receiptId, string $lineId, ?string $text = null, ?string $unit = null, ?int $quantity = null, ?int $amountCents = null): void
    {
        (new UpdateLineHandler($this->updater(), $this->lineLinker()))(new UpdateLineCommand(self::MARIA, self::LIST, $receiptId, $lineId, $text, $unit, $quantity, $amountCents));
    }

    private function link(string $receiptId, string $lineId, string $productId): void
    {
        (new LinkLineHandler($this->updater(), $this->catalog))(new LinkLineCommand(self::MARIA, self::LIST, $receiptId, $lineId, $productId));
    }

    private function delete(string $actor, string $receiptId): void
    {
        (new DeleteReceiptHandler($this->updater(), $this->receipts, $this->photos))(new DeleteReceiptCommand($actor, self::LIST, $receiptId));
        self::assertNull($this->receipts->findById(ReceiptId::fromString($receiptId)));
    }
}
