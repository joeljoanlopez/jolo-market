<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Receipts\Application\ReceiptProductCatalog;
use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Shared\Domain\ValueObject\InvalidUuid;

/**
 * PD-67: a member accepts the suggestion or picks another product, which must be a product of the
 * receipt's supermarket, available or not.
 */
final readonly class LinkLineHandler
{
    public function __construct(
        private ReceiptUpdater $updater,
        private ReceiptProductCatalog $catalog,
    ) {
    }

    public function __invoke(LinkLineCommand $command): void
    {
        $lineId = ReceiptUpdater::lineId($command->lineId);

        $this->updater->update($command->actorId, $command->listId, $command->receiptId, function (Receipt $receipt) use ($command, $lineId): void {
            $supermarketId = $receipt->supermarketId()->value();
            try {
                $productId = ProductId::fromString($command->productId);
            } catch (InvalidUuid) {
                throw ProductNotOfSupermarket::withId($command->productId, $supermarketId);
            }
            $product = $this->catalog->findByIds([$productId])[$productId->value()] ?? null;
            if (null === $product || $product->supermarketId !== $supermarketId) {
                throw ProductNotOfSupermarket::withId($command->productId, $supermarketId);
            }

            $receipt->linkProduct($lineId, $productId);
        });
    }
}
