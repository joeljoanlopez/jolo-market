<?php

declare(strict_types=1);

namespace App\Receipts\Application\ReadReceipt;

use App\Receipts\Application\LineLinker;
use App\Receipts\Domain\Model\ReadResult;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Model\ReceiptLine;
use App\Receipts\Domain\Port\ReceiptChangedConcurrently;
use App\Receipts\Domain\Port\ReceiptPhotoStorage;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\QuantityUnit;
use App\Receipts\Domain\ValueObject\ReadBy;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptId;
use App\Receipts\Domain\ValueObject\ReceiptLineId;
use App\Receipts\Domain\ValueObject\ReceiptStatus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\Money;

/**
 * Run by the worker (PD-65): reads the photo and turns the receipt into a draft with what was
 * read, then links the lines the memory knows and suggests a product for the others (PD-67).
 * When reading fails the draft is empty. A receipt deleted meanwhile is skipped (PD-129).
 */
final readonly class ReadReceiptHandler
{
    public function __construct(
        private ReceiptRepository $receipts,
        private ReceiptPhotoStorage $photos,
        private ReceiptReader $reader,
        private LineLinker $linker,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReadReceiptCommand $command): void
    {
        $receipt = $this->receipts->findById(ReceiptId::fromString($command->receiptId));
        if (null === $receipt || ReceiptStatus::READING !== $receipt->status()) {
            return;
        }

        $receipt->completeReading($this->resultFor($receipt), $this->clock->now());
        foreach ($receipt->lines() as $line) {
            $this->linker->apply($receipt, $line->id());
        }

        try {
            $this->receipts->save($receipt);
        } catch (ReceiptChangedConcurrently) {
            // Only deleting changes a receipt that is being read, so there is nothing left to save.
        }
    }

    private function resultFor(Receipt $receipt): ReadResult
    {
        $photo = $this->photos->read($receipt->id());
        $data = null === $photo ? null : $this->reader->read($photo, $receipt->photoType(), $receipt->supermarketId());
        if (null === $data || ReadBy::NONE === $data->readBy) {
            return ReadResult::failed();
        }

        $lines = [];
        foreach ($data->lines as $lineData) {
            $line = $this->lineFrom($lineData);
            if (null !== $line && \count($lines) < Receipt::MAX_LINES) {
                $lines[] = $line;
            }
        }

        return ReadResult::read($data->readBy, self::dateFrom($data->date), self::positive($data->totalCents), $lines);
    }

    /**
     * Anything that breaks the receipt rules is left out rather than guessed (PD-62).
     */
    private function lineFrom(ReadLineData $data): ?ReceiptLine
    {
        $amount = self::positive($data->amountCents);
        if (null === $amount) {
            return null;
        }

        try {
            $id = ReceiptLineId::fromString($this->ids->generate()->value());
            $text = LineText::fromString($data->text);
            if ('discount' === $data->kind) {
                return ReceiptLine::discount($id, $text, $amount);
            }
            $unit = null === $data->unit ? null : QuantityUnit::tryFrom($data->unit);
            if (null === $unit || null === $data->quantity) {
                return null;
            }

            return ReceiptLine::product($id, $text, LineQuantity::of($unit, $data->quantity), $amount);
        } catch (DomainError) {
            return null;
        }
    }

    private static function dateFrom(?string $date): ?ReceiptDate
    {
        try {
            return null === $date ? null : ReceiptDate::fromString($date);
        } catch (DomainError) {
            return null;
        }
    }

    private static function positive(?int $cents): ?Money
    {
        return null === $cents || $cents <= 0 ? null : Money::eur($cents);
    }
}
