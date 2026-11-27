<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Receipts\Application\LineLinker;
use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Domain\Model\InvalidReceiptAmount;
use App\Receipts\Domain\Model\LineKind;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ReceiptLineId;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\Money;

/**
 * PD-66: any member adds a product or discount line. A product line is linked by the memory or
 * gets a suggestion, like a line read from the photo (PD-128).
 */
final readonly class AddLineHandler
{
    public function __construct(
        private ReceiptUpdater $updater,
        private LineLinker $linker,
        private IdGenerator $ids,
    ) {
    }

    /**
     * @return string the new line id
     */
    public function __invoke(AddLineCommand $command): string
    {
        $kind = LineKind::tryFrom($command->kind) ?? throw InvalidLine::unknownKind($command->kind);
        $text = LineText::fromString($command->text);
        $quantity = LineQuantities::from($command->unit, $command->quantity);
        if (LineKind::PRODUCT === $kind && null === $quantity) {
            throw InvalidLine::quantityNeeded();
        }
        if (LineKind::DISCOUNT === $kind && null !== $quantity) {
            throw InvalidLine::noQuantityForDiscount();
        }
        if ($command->amountCents <= 0) {
            throw InvalidReceiptAmount::notPositive();
        }
        $amount = Money::eur($command->amountCents);

        return $this->updater->update($command->actorId, $command->listId, $command->receiptId, function (Receipt $receipt) use ($text, $quantity, $amount): string {
            $lineId = ReceiptLineId::fromString($this->ids->generate()->value());
            if (null === $quantity) {
                $receipt->addDiscountLine($lineId, $text, $amount);
            } else {
                $receipt->addProductLine($lineId, $text, $quantity, $amount);
                $this->linker->apply($receipt, $lineId);
            }

            return $lineId->value();
        });
    }
}
