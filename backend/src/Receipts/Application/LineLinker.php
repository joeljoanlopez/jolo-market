<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Port\ProductLinkMemory;
use App\Receipts\Domain\ValueObject\ReceiptLineId;

/**
 * PD-67, PD-128: a product line whose text the memory knows for the receipt's supermarket is
 * linked to that product; any other gets the most similar product as a suggestion only.
 */
final readonly class LineLinker
{
    public function __construct(
        private ProductLinkMemory $memory,
        private ReceiptProductCatalog $catalog,
    ) {
    }

    public function apply(Receipt $receipt, ReceiptLineId $lineId): void
    {
        foreach ($receipt->lines() as $line) {
            if (!$line->id()->equals($lineId) || !$line->isProduct()) {
                continue;
            }

            $remembered = $this->memory->find($receipt->supermarketId(), $line->text());
            if (null !== $remembered) {
                $receipt->linkProduct($lineId, $remembered);

                return;
            }

            $suggested = $this->catalog->bestMatch($receipt->supermarketId(), $line->text());
            if (null !== $suggested) {
                $receipt->suggestProduct($lineId, $suggested);
            }

            return;
        }
    }
}
