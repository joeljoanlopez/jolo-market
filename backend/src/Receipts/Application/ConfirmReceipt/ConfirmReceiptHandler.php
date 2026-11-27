<?php

declare(strict_types=1);

namespace App\Receipts\Application\ConfirmReceipt;

use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Port\ProductLinkMemory;

/**
 * PD-65, PD-67, PD-130: a member confirms the receipt, and "text → product" of every linked line
 * is remembered for its supermarket, so the last confirmation wins. Confirming again remembers
 * them again. It never touches list items, catalog prices or the price history (PD-71).
 */
final readonly class ConfirmReceiptHandler
{
    public function __construct(
        private ReceiptUpdater $updater,
        private ProductLinkMemory $memory,
    ) {
    }

    public function __invoke(ConfirmReceiptCommand $command): void
    {
        $receipt = $this->updater->update($command->actorId, $command->listId, $command->receiptId, static function (Receipt $receipt): Receipt {
            $receipt->confirm();

            return $receipt;
        });

        foreach ($receipt->lines() as $line) {
            $productId = $line->linkedProductId();
            if (null !== $productId) {
                $this->memory->remember($receipt->supermarketId(), $line->text(), $productId);
            }
        }
    }
}
