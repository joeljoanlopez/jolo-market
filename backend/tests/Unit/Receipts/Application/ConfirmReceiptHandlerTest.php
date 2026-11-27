<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Application;

use App\Receipts\Application\ConfirmReceipt\ConfirmReceiptCommand;
use App\Receipts\Application\ConfirmReceipt\ConfirmReceiptHandler;
use App\Receipts\Application\EditReceipt\AddLineCommand;
use App\Receipts\Application\EditReceipt\AddLineHandler;
use App\Receipts\Application\EditReceipt\LinkLineCommand;
use App\Receipts\Application\EditReceipt\LinkLineHandler;
use App\Receipts\Application\EditReceipt\UpdateReceiptCommand;
use App\Receipts\Application\EditReceipt\UpdateReceiptHandler;
use App\Receipts\Application\ReceiptListNotFound;
use App\Receipts\Domain\Model\ReceiptNotConfirmable;
use App\Receipts\Domain\Model\ReceiptStillReading;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use PHPUnit\Framework\Attributes\Test;

final class ConfirmReceiptHandlerTest extends ReceiptHandlersTestCase
{
    #[Test]
    public function confirming_remembers_the_linked_lines_but_not_the_suggested_ones(): void
    {
        $receiptId = $this->completeDraft();
        $bread = $this->addLine($receiptId, 'PAN MOLDE');
        $this->link($receiptId, $bread, self::BREAD);
        $this->addLine($receiptId, 'LECHE ENT.');

        $this->confirm(self::JOAN, $receiptId);

        self::assertSame(ReceiptStatus::CONFIRMED, $this->receipt($receiptId)->status());
        self::assertSame(['mercadona|pan molde' => self::BREAD], $this->memory->all());
    }

    #[Test]
    public function a_later_receipt_of_the_same_supermarket_links_the_text_by_itself_in_any_list(): void
    {
        $receiptId = $this->completeDraft();
        $this->link($receiptId, $this->addLine($receiptId, 'PAN MOLDE'), self::BREAD);
        $this->confirm(self::MARIA, $receiptId);

        $this->access->allow(self::OTHER_LIST, self::MARIA);
        $later = $this->upload(listId: self::OTHER_LIST);
        $this->reader->willRead(null);
        (new \App\Receipts\Application\ReadReceipt\ReadReceiptHandler($this->receipts, $this->photos, $this->reader, $this->lineLinker(), $this->ids, $this->clock))(new \App\Receipts\Application\ReadReceipt\ReadReceiptCommand($later));
        $line = (new AddLineHandler($this->updater(), $this->lineLinker(), $this->ids))(new AddLineCommand(self::MARIA, self::OTHER_LIST, $later, 'product', 'Pan Molde', 'units', 1, 100));

        self::assertSame(self::BREAD, $this->receipt($later)->lines()[0]->linkedProductId()?->value());
        self::assertSame($line, $this->receipt($later)->lines()[0]->id()->value());
    }

    #[Test]
    public function the_last_confirmation_wins_also_when_a_confirmed_receipt_is_confirmed_again(): void
    {
        $this->catalog->add('0191bbbb-0000-7000-8000-000000000009', 'mercadona', 'Pan integral');
        $receiptId = $this->completeDraft();
        $line = $this->addLine($receiptId, 'PAN');
        $this->link($receiptId, $line, self::BREAD);
        $this->confirm(self::MARIA, $receiptId);

        $this->link($receiptId, $line, '0191bbbb-0000-7000-8000-000000000009');
        self::assertSame(['mercadona|pan' => self::BREAD], $this->memory->all());

        $this->confirm(self::MARIA, $receiptId);
        self::assertSame(['mercadona|pan' => '0191bbbb-0000-7000-8000-000000000009'], $this->memory->all());
    }

    #[Test]
    public function a_receipt_needs_a_date_and_a_total(): void
    {
        $receiptId = $this->draft();

        $this->expectException(ReceiptNotConfirmable::class);

        $this->confirm(self::MARIA, $receiptId);
    }

    #[Test]
    public function a_receipt_still_being_read_is_not_confirmed(): void
    {
        $this->expectException(ReceiptStillReading::class);

        $this->confirm(self::MARIA, $this->upload());
    }

    #[Test]
    public function an_outsider_cannot_confirm(): void
    {
        $receiptId = $this->completeDraft();

        $this->expectException(ReceiptListNotFound::class);

        $this->confirm(self::PEDRO, $receiptId);
    }

    private function completeDraft(): string
    {
        $receiptId = $this->draft();
        (new UpdateReceiptHandler($this->updater(), $this->clock))(new UpdateReceiptCommand(self::MARIA, self::LIST, $receiptId, '2026-11-14', 1000));

        return $receiptId;
    }

    private function addLine(string $receiptId, string $text): string
    {
        return (new AddLineHandler($this->updater(), $this->lineLinker(), $this->ids))(new AddLineCommand(self::MARIA, self::LIST, $receiptId, 'product', $text, 'units', 1, 100));
    }

    private function link(string $receiptId, string $lineId, string $productId): void
    {
        (new LinkLineHandler($this->updater(), $this->catalog))(new LinkLineCommand(self::MARIA, self::LIST, $receiptId, $lineId, $productId));
    }

    private function confirm(string $actor, string $receiptId): void
    {
        (new ConfirmReceiptHandler($this->updater(), $this->memory))(new ConfirmReceiptCommand($actor, self::LIST, $receiptId));
    }
}
