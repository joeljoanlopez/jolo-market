<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceiptPhoto;

use App\Receipts\Application\ReceiptNotFound;
use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Domain\Port\ReceiptPhotoStorage;

/**
 * PD-63, PD-68: the photo, shown next to the receipt to the owner and members of its list only.
 */
final readonly class GetReceiptPhotoHandler
{
    public function __construct(
        private ReceiptUpdater $updater,
        private ReceiptPhotoStorage $photos,
    ) {
    }

    public function __invoke(GetReceiptPhotoQuery $query): ReceiptPhoto
    {
        $receipt = $this->updater->load($query->actorId, $query->listId, $query->receiptId);
        $contents = $this->photos->read($receipt->id()) ?? throw ReceiptNotFound::withId($query->receiptId);

        return new ReceiptPhoto($contents, $receipt->photoType()->mediaType());
    }
}
