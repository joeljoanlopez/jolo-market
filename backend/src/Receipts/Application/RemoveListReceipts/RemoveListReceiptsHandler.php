<?php

declare(strict_types=1);

namespace App\Receipts\Application\RemoveListReceipts;

use App\Receipts\Domain\Port\ReceiptPhotoStorage;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Receipts\Domain\ValueObject\ListId;
use App\Shared\Domain\ValueObject\InvalidUuid;

/**
 * PD-63: when a list is deleted, its receipts and their photos go with it. Running it again is safe.
 */
final readonly class RemoveListReceiptsHandler
{
    public function __construct(
        private ReceiptRepository $receipts,
        private ReceiptPhotoStorage $photos,
    ) {
    }

    public function __invoke(RemoveListReceiptsCommand $command): void
    {
        try {
            $listId = ListId::fromString($command->listId);
        } catch (InvalidUuid) {
            return;
        }

        foreach ($this->receipts->findByList($listId) as $receipt) {
            $this->photos->delete($receipt->id());
        }
        $this->receipts->removeByList($listId);
    }
}
