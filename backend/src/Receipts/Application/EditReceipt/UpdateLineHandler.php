<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Receipts\Application\LineLinker;
use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Domain\Model\InvalidReceiptAmount;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Model\ReceiptLine;
use App\Receipts\Domain\Model\ReceiptLineNotFound;
use App\Receipts\Domain\ValueObject\LineText;
use App\Shared\Domain\ValueObject\Money;

/**
 * PD-66: any member edits the text, quantity or amount of a line. A new text is linked by the
 * memory or gets a suggestion again; other edits keep the link (PD-128).
 */
final readonly class UpdateLineHandler
{
    public function __construct(
        private ReceiptUpdater $updater,
        private LineLinker $linker,
    ) {
    }

    public function __invoke(UpdateLineCommand $command): void
    {
        $lineId = ReceiptUpdater::lineId($command->lineId);
        $text = null === $command->text ? null : LineText::fromString($command->text);
        $quantity = LineQuantities::from($command->unit, $command->quantity);
        if (null !== $command->amountCents && $command->amountCents <= 0) {
            throw InvalidReceiptAmount::notPositive();
        }
        $amount = null === $command->amountCents ? null : Money::eur($command->amountCents);

        $this->updater->update($command->actorId, $command->listId, $command->receiptId, function (Receipt $receipt) use ($lineId, $text, $quantity, $amount): void {
            $line = self::line($receipt, $lineId->value());
            if (!$line->isProduct() && null !== $quantity) {
                throw InvalidLine::noQuantityForDiscount();
            }
            $newText = $text ?? $line->text();
            $receipt->updateLine($lineId, $newText, $quantity ?? $line->quantity(), $amount ?? $line->amount());

            if ($newText->value() !== $line->text()->value()) {
                $this->linker->apply($receipt, $lineId);
            }
        });
    }

    private static function line(Receipt $receipt, string $lineId): ReceiptLine
    {
        foreach ($receipt->lines() as $line) {
            if ($line->id()->value() === $lineId) {
                return clone $line;
            }
        }

        throw ReceiptLineNotFound::withId($lineId);
    }
}
