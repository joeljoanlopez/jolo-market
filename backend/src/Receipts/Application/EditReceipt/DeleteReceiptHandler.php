<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Domain\Port\ReceiptPhotoStorage;
use App\Receipts\Domain\Port\ReceiptRepository;

/**
 * PD-65, PD-129: any member deletes a receipt in any status, with its photo.
 */
final readonly class DeleteReceiptHandler
{
    public function __construct(
        private ReceiptUpdater $updater,
        private ReceiptRepository $receipts,
        private ReceiptPhotoStorage $photos,
    ) {
    }

    public function __invoke(DeleteReceiptCommand $command): void
    {
        $receipt = $this->updater->load($command->actorId, $command->listId, $command->receiptId);

        $this->receipts->remove($receipt->id());
        $this->photos->delete($receipt->id());
    }
}
