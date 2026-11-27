<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Domain\Model\Receipt;

/**
 * PD-67: a member leaves a product line without a product.
 */
final readonly class UnlinkLineHandler
{
    public function __construct(private ReceiptUpdater $updater)
    {
    }

    public function __invoke(UnlinkLineCommand $command): void
    {
        $lineId = ReceiptUpdater::lineId($command->lineId);

        $this->updater->update($command->actorId, $command->listId, $command->receiptId, static fn (Receipt $receipt) => $receipt->unlink($lineId));
    }
}
