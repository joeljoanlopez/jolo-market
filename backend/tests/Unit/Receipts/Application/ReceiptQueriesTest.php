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
use App\Receipts\Application\GetListReceipts\GetListReceiptsHandler;
use App\Receipts\Application\GetListReceipts\GetListReceiptsQuery;
use App\Receipts\Application\GetListReceipts\ReceiptSummary;
use App\Receipts\Application\GetListSpending\GetListSpendingHandler;
use App\Receipts\Application\GetListSpending\GetListSpendingQuery;
use App\Receipts\Application\GetListSpending\MonthlySpending;
use App\Receipts\Application\GetListSpending\SupermarketSpending;
use App\Receipts\Application\GetReceipt\GetReceiptHandler;
use App\Receipts\Application\GetReceipt\GetReceiptQuery;
use App\Receipts\Application\GetReceipt\ReceiptLineProductView;
use App\Receipts\Application\GetReceipt\ReceiptLineView;
use App\Receipts\Application\GetReceipt\ReceiptUserView;
use App\Receipts\Application\ReceiptListNotFound;
use App\Receipts\Application\ReceiptNotFound;
use App\Tests\Support\Receipts\InMemorySpendingReadModel;
use PHPUnit\Framework\Attributes\Test;

final class ReceiptQueriesTest extends ReceiptHandlersTestCase
{
    #[Test]
    public function a_member_sees_the_receipt_with_its_lines_products_and_difference(): void
    {
        $receiptId = $this->draft(self::JOAN);
        $this->updateReceipt($receiptId, '2026-11-14', 1000);
        $milk = $this->addLine($receiptId, 'product', 'LECHE ENT.', 'units', 6, 534);
        $bread = $this->addLine($receiptId, 'product', 'PAN MOLDE', 'kg', 250, 300);
        $this->link($receiptId, $bread, self::BREAD);
        $other = $this->addLine($receiptId, 'product', 'DETERGENTE', 'units', 1, 399);
        $discount = $this->addLine($receiptId, 'discount', 'DTO', null, null, 50);

        $view = ($this->getReceipt())(new GetReceiptQuery(self::MARIA, self::LIST, $receiptId));

        self::assertSame($receiptId, $view->id);
        self::assertSame('draft', $view->status);
        self::assertSame('mercadona', $view->supermarketId);
        self::assertSame('Mercadona', $view->supermarketName);
        self::assertSame('2026-11-14', $view->date);
        self::assertSame(1000, $view->totalCents);
        self::assertEquals(new ReceiptUserView(self::JOAN, 'joan'), $view->uploader);
        self::assertSame('2026-11-14T18:00:00+00:00', $view->uploadedAt);
        self::assertSame(534 + 300 + 399 - 50 - 1000, $view->differenceCents);
        self::assertEquals([
            new ReceiptLineView($milk, 'product', 'LECHE ENT.', 'units', 6, 534, null, new ReceiptLineProductView(self::MILK, 'Leche entera', 'Hacendado', true)),
            new ReceiptLineView($bread, 'product', 'PAN MOLDE', 'kg', 250, 300, new ReceiptLineProductView(self::BREAD, 'Pan de molde', 'Hacendado', false), null),
            new ReceiptLineView($other, 'product', 'DETERGENTE', 'units', 1, 399, null, null),
            new ReceiptLineView($discount, 'discount', 'DTO', null, null, 50, null, null),
        ], $view->lines);
    }

    #[Test]
    public function a_linked_product_the_catalog_no_longer_knows_is_shown_by_the_line_text(): void
    {
        $this->catalog->add('0191bbbb-0000-7000-8000-000000000008', 'mercadona', 'Pan viejo');
        $receiptId = $this->draft();
        $line = $this->addLine($receiptId, 'product', 'PAN VIEJO', 'units', 1, 100);
        $this->link($receiptId, $line, '0191bbbb-0000-7000-8000-000000000008');
        $this->catalog = new \App\Tests\Support\Receipts\InMemoryReceiptProductCatalog();

        $view = ($this->getReceipt())(new GetReceiptQuery(self::MARIA, self::LIST, $receiptId));

        self::assertEquals(new ReceiptLineProductView('0191bbbb-0000-7000-8000-000000000008', null, null, false), $view->lines[0]->linkedProduct);
        self::assertSame('PAN VIEJO', $view->lines[0]->text);
    }

    #[Test]
    public function a_receipt_being_read_has_no_date_total_or_difference(): void
    {
        $view = ($this->getReceipt())(new GetReceiptQuery(self::MARIA, self::LIST, $this->upload()));

        self::assertSame('reading', $view->status);
        self::assertNull($view->date);
        self::assertNull($view->totalCents);
        self::assertNull($view->differenceCents);
        self::assertSame([], $view->lines);
    }

    #[Test]
    public function an_outsider_or_another_list_does_not_see_the_receipt(): void
    {
        $receiptId = $this->draft();

        $this->expectExceptionIn(ReceiptListNotFound::class, fn () => ($this->getReceipt())(new GetReceiptQuery(self::PEDRO, self::LIST, $receiptId)));
        $this->expectExceptionIn(ReceiptNotFound::class, fn () => ($this->getReceipt())(new GetReceiptQuery(self::PEDRO, self::OTHER_LIST, $receiptId)));
        $this->expectExceptionIn(ReceiptListNotFound::class, fn () => ($this->listReceipts())(new GetListReceiptsQuery(self::PEDRO, self::LIST)));
        $this->expectExceptionIn(ReceiptListNotFound::class, fn () => ($this->spending())(new GetListSpendingQuery(self::PEDRO, self::LIST)));
    }

    #[Test]
    public function the_list_shows_pending_receipts_apart_from_confirmed_ones_newest_first(): void
    {
        $older = $this->confirmed('2026-11-02', 1000);
        $this->clock->advance(new \DateInterval('PT1M'));
        $newer = $this->confirmed('2026-11-10', 2000, 'aldi');
        $this->clock->advance(new \DateInterval('PT1M'));
        $sameDayLaterUpload = $this->confirmed('2026-11-10', 500);
        $this->clock->advance(new \DateInterval('PT1M'));
        $draft = $this->draft();
        $this->clock->advance(new \DateInterval('PT1M'));
        $reading = $this->upload(self::JOAN);

        $view = ($this->listReceipts())(new GetListReceiptsQuery(self::JOAN, self::LIST));

        self::assertSame([$reading, $draft], array_map(static fn (ReceiptSummary $receipt): string => $receipt->id, $view->pending));
        self::assertSame(['reading', 'draft'], array_map(static fn (ReceiptSummary $receipt): string => $receipt->status, $view->pending));
        self::assertSame([$sameDayLaterUpload, $newer, $older], array_map(static fn (ReceiptSummary $receipt): string => $receipt->id, $view->confirmed));
        self::assertEquals(
            new ReceiptSummary($newer, 'confirmed', 'aldi', 'Aldi', '2026-11-10', 2000, new ReceiptUserView(self::MARIA, 'maria'), '2026-11-14T18:01:00+00:00'),
            $view->confirmed[1],
        );
    }

    #[Test]
    public function spending_adds_up_confirmed_receipts_per_month_and_supermarket_newest_month_first(): void
    {
        $this->clock = \App\Tests\Support\FixedClock::at('2026-12-20 10:00:00');
        $this->confirmed('2026-11-02', 1000);
        $this->confirmed('2026-11-30', 250);
        $this->confirmed('2026-11-15', 2000, 'aldi');
        $this->confirmed('2026-12-01', 700);
        $draft = $this->draft();
        $this->updateReceipt($draft, '2026-12-02', 9999);
        $this->upload();

        $spending = ($this->spending())(new GetListSpendingQuery(self::MARIA, self::LIST));

        self::assertEquals([
            new MonthlySpending('2026-12', 700, [new SupermarketSpending('mercadona', 'Mercadona', 700)]),
            new MonthlySpending('2026-11', 3250, [
                new SupermarketSpending('aldi', 'Aldi', 2000),
                new SupermarketSpending('mercadona', 'Mercadona', 1250),
            ]),
        ], $spending);
    }

    #[Test]
    public function a_list_without_confirmed_receipts_has_no_spending(): void
    {
        $this->draft();

        self::assertSame([], ($this->spending())(new GetListSpendingQuery(self::MARIA, self::LIST)));
    }

    private function confirmed(string $date, int $totalCents, string $supermarket = 'mercadona'): string
    {
        $receiptId = $this->draft(supermarket: $supermarket);
        $this->updateReceipt($receiptId, $date, $totalCents);
        (new ConfirmReceiptHandler($this->updater(), $this->memory))(new ConfirmReceiptCommand(self::MARIA, self::LIST, $receiptId));

        return $receiptId;
    }

    private function updateReceipt(string $receiptId, string $date, int $totalCents): void
    {
        (new UpdateReceiptHandler($this->updater(), $this->clock))(new UpdateReceiptCommand(self::MARIA, self::LIST, $receiptId, $date, $totalCents));
    }

    private function addLine(string $receiptId, string $kind, string $text, ?string $unit, ?int $quantity, int $amountCents): string
    {
        return (new AddLineHandler($this->updater(), $this->lineLinker(), $this->ids))(new AddLineCommand(self::MARIA, self::LIST, $receiptId, $kind, $text, $unit, $quantity, $amountCents));
    }

    private function link(string $receiptId, string $lineId, string $productId): void
    {
        (new LinkLineHandler($this->updater(), $this->catalog))(new LinkLineCommand(self::MARIA, self::LIST, $receiptId, $lineId, $productId));
    }

    private function getReceipt(): GetReceiptHandler
    {
        return new GetReceiptHandler($this->updater(), $this->catalog, $this->users, $this->supermarkets);
    }

    private function listReceipts(): GetListReceiptsHandler
    {
        return new GetListReceiptsHandler($this->access, $this->receipts, $this->users, $this->supermarkets);
    }

    private function spending(): GetListSpendingHandler
    {
        return new GetListSpendingHandler($this->access, new InMemorySpendingReadModel($this->receipts), $this->supermarkets);
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
}
