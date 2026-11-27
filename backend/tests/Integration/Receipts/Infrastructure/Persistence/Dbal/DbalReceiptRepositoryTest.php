<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Persistence\Dbal;

use App\Receipts\Domain\Model\LineKind;
use App\Receipts\Domain\Model\ReadResult;
use App\Receipts\Domain\Model\ReceiptLine;
use App\Receipts\Domain\Port\ReceiptChangedConcurrently;
use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\QuantityUnit;
use App\Receipts\Domain\ValueObject\ReadBy;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\ReceiptLineId;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;

final class DbalReceiptRepositoryTest extends ReceiptsDbalTestCase
{
    #[Test]
    public function it_stores_and_loads_a_receipt_being_read(): void
    {
        $this->repository->save(self::uploaded(1));

        $receipt = $this->load(1);
        self::assertSame(self::LIST, $receipt->listId()->value());
        self::assertSame('mercadona', $receipt->supermarketId()->value());
        self::assertSame(self::MARIA, $receipt->uploaderId()->value());
        self::assertSame(PhotoType::PNG, $receipt->photoType());
        self::assertEquals(new \DateTimeImmutable('2026-11-14 18:00:00.123456', new \DateTimeZone('UTC')), $receipt->uploadedAt());
        self::assertSame(ReceiptStatus::READING, $receipt->status());
        self::assertNull($receipt->readBy());
        self::assertNull($receipt->date());
        self::assertNull($receipt->total());
        self::assertSame([], $receipt->lines());
        self::assertSame(1, $receipt->loadedVersion());
    }

    #[Test]
    public function it_stores_a_draft_with_its_lines_in_order_and_their_products(): void
    {
        $receipt = self::uploaded(1);
        $this->repository->save($receipt);
        $receipt = $this->load(1);
        $receipt->completeReading(ReadResult::read(ReadBy::OCR, ReceiptDate::fromString('2026-11-14'), Money::eur(1234), [
            ReceiptLine::product(self::lineId(2), LineText::fromString('PLÁTANO'), LineQuantity::kilograms(532), Money::eur(120)),
            ReceiptLine::product(self::lineId(1), LineText::fromString('LECHE'), LineQuantity::units(6), Money::eur(534)),
            ReceiptLine::discount(self::lineId(3), LineText::fromString('DTO'), Money::eur(50)),
        ]), new \DateTimeImmutable('2026-11-14 19:00:00'));
        $receipt->linkProduct(self::lineId(1), ProductId::fromString(self::MILK));
        $receipt->suggestProduct(self::lineId(2), ProductId::fromString(self::BREAD));
        $this->repository->save($receipt);

        $loaded = $this->load(1);
        self::assertSame(ReceiptStatus::DRAFT, $loaded->status());
        self::assertSame(ReadBy::OCR, $loaded->readBy());
        self::assertSame('2026-11-14', $loaded->date()?->value());
        self::assertSame(1234, $loaded->total()?->cents());
        self::assertSame($receipt->version(), $loaded->loadedVersion());
        [$banana, $milk, $discount] = $loaded->lines();
        self::assertSame(self::lineId(2)->value(), $banana->id()->value());
        self::assertSame('PLÁTANO', $banana->text()->value());
        self::assertSame(QuantityUnit::KG, $banana->quantity()?->unit());
        self::assertSame(532, $banana->quantity()->value());
        self::assertSame(120, $banana->amount()->cents());
        self::assertNull($banana->linkedProductId());
        self::assertSame(self::BREAD, $banana->suggestedProductId()?->value());
        self::assertSame(self::MILK, $milk->linkedProductId()?->value());
        self::assertSame(LineKind::DISCOUNT, $discount->kind());
        self::assertNull($discount->quantity());
    }

    #[Test]
    public function saving_rewrites_the_lines(): void
    {
        $this->repository->save(self::uploaded(1));
        $receipt = $this->load(1);
        $receipt->completeReading(ReadResult::failed(), new \DateTimeImmutable());
        $receipt->addDiscountLine(self::lineId(1), LineText::fromString('DTO'), Money::eur(1));
        $this->repository->save($receipt);

        $receipt = $this->load(1);
        $receipt->removeLine(self::lineId(1));
        $receipt->addDiscountLine(self::lineId(2), LineText::fromString('DTO 2'), Money::eur(2));
        $this->repository->save($receipt);

        $lines = $this->load(1)->lines();
        self::assertCount(1, $lines);
        self::assertSame('DTO 2', $lines[0]->text()->value());
    }

    #[Test]
    public function saving_a_receipt_someone_else_saved_or_deleted_meanwhile_fails(): void
    {
        $this->repository->save(self::uploaded(1));
        $first = $this->load(1);
        $second = $this->load(1);
        $first->completeReading(ReadResult::failed(), new \DateTimeImmutable());
        $second->completeReading(ReadResult::failed(), new \DateTimeImmutable());
        $this->repository->save($first);

        try {
            $this->repository->save($second);
            self::fail('Expected a ReceiptChangedConcurrently.');
        } catch (ReceiptChangedConcurrently) {
            $this->addToAssertionCount(1);
        }

        $this->repository->remove(ReceiptId::fromString(self::receiptId(1)));
        $this->expectException(ReceiptChangedConcurrently::class);
        $this->repository->save($first);
    }

    #[Test]
    public function it_finds_and_removes_the_receipts_of_a_list(): void
    {
        $this->repository->save(self::uploaded(1));
        $this->repository->save(self::uploaded(2));
        $this->repository->save(self::uploaded(3, self::OTHER_LIST));

        self::assertCount(2, $this->repository->findByList(ListId::fromString(self::LIST)));

        $this->repository->removeByList(ListId::fromString(self::LIST));

        self::assertSame([], $this->repository->findByList(ListId::fromString(self::LIST)));
        self::assertCount(1, $this->repository->findByList(ListId::fromString(self::OTHER_LIST)));
        self::assertSame(0, $this->countRows('receipt_line'));
    }

    #[Test]
    public function removing_a_receipt_removes_its_lines(): void
    {
        $this->confirmed(1, '2026-11-14', 100);
        $receipt = $this->load(1);
        $receipt->addDiscountLine(self::lineId(1), LineText::fromString('DTO'), Money::eur(1));
        $this->repository->save($receipt);

        $this->repository->remove(ReceiptId::fromString(self::receiptId(1)));

        self::assertNull($this->repository->findById(ReceiptId::fromString(self::receiptId(1))));
        self::assertSame(0, $this->countRows('receipt_line'));
    }

    private static function lineId(int $n): ReceiptLineId
    {
        return ReceiptLineId::fromString(\sprintf('0193cccc-0000-7000-8000-%012d', $n));
    }
}
