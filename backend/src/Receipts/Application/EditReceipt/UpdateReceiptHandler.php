<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Domain\Model\InvalidReceiptAmount;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\Money;

/**
 * PD-66: any member edits the date and the total of a draft or a confirmed receipt.
 */
final readonly class UpdateReceiptHandler
{
    public function __construct(
        private ReceiptUpdater $updater,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateReceiptCommand $command): void
    {
        $date = null === $command->date ? null : ReceiptDate::fromString($command->date);
        if (null !== $command->totalCents && $command->totalCents <= 0) {
            throw InvalidReceiptAmount::notPositive();
        }
        $total = null === $command->totalCents ? null : Money::eur($command->totalCents);

        $this->updater->update($command->actorId, $command->listId, $command->receiptId, function (Receipt $receipt) use ($date, $total): void {
            if (null !== $date) {
                $receipt->changeDate($date, $this->clock->now());
            }
            if (null !== $total) {
                $receipt->changeTotal($total);
            }
        });
    }
}
