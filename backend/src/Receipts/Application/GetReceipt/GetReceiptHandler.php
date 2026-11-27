<?php

declare(strict_types=1);

namespace App\Receipts\Application\GetReceipt;

use App\Receipts\Application\ReceiptProduct;
use App\Receipts\Application\ReceiptProductCatalog;
use App\Receipts\Application\ReceiptSupermarkets;
use App\Receipts\Application\ReceiptUpdater;
use App\Receipts\Application\ReceiptUserDirectory;
use App\Receipts\Application\ReceiptViews;
use App\Receipts\Domain\Model\ReceiptLine;
use App\Receipts\Domain\ValueObject\ProductId;

/**
 * A receipt for its review (PD-66, PD-67): its lines with the linked or suggested product and
 * the difference between lines and total.
 */
final readonly class GetReceiptHandler
{
    public function __construct(
        private ReceiptUpdater $updater,
        private ReceiptProductCatalog $catalog,
        private ReceiptUserDirectory $users,
        private ReceiptSupermarkets $supermarkets,
    ) {
    }

    public function __invoke(GetReceiptQuery $query): ReceiptView
    {
        $receipt = $this->updater->load($query->actorId, $query->listId, $query->receiptId);

        $productIds = [];
        foreach ($receipt->lines() as $line) {
            foreach ([$line->linkedProductId(), $line->suggestedProductId()] as $productId) {
                if (null !== $productId) {
                    $productIds[$productId->value()] = $productId;
                }
            }
        }
        $products = [] === $productIds ? [] : $this->catalog->findByIds(array_values($productIds));

        return new ReceiptView(
            $receipt->id()->value(),
            $receipt->status()->value,
            $receipt->supermarketId()->value(),
            ReceiptViews::supermarketName($receipt, $this->supermarkets->namesOf([$receipt->supermarketId()])),
            $receipt->date()?->value(),
            $receipt->total()?->cents(),
            ReceiptViews::uploader($receipt, $this->users->usernamesOf([$receipt->uploaderId()])),
            ReceiptViews::uploadedAt($receipt),
            $receipt->difference()?->signedCents(),
            array_map(static fn (ReceiptLine $line): ReceiptLineView => self::lineView($line, $products), $receipt->lines()),
        );
    }

    /**
     * @param array<string, ReceiptProduct> $products
     */
    private static function lineView(ReceiptLine $line, array $products): ReceiptLineView
    {
        $linked = $line->linkedProductId();
        $suggested = $line->suggestedProductId();
        $suggestedProduct = null === $suggested ? null : ($products[$suggested->value()] ?? null);

        return new ReceiptLineView(
            $line->id()->value(),
            $line->kind()->value,
            $line->text()->value(),
            $line->quantity()?->unit()->value,
            $line->quantity()?->value(),
            $line->amount()->cents(),
            null === $linked ? null : self::productView($linked, $products[$linked->value()] ?? null),
            // A suggestion the catalog no longer knows is no suggestion at all.
            null === $suggested || null === $suggestedProduct ? null : self::productView($suggested, $suggestedProduct),
        );
    }

    private static function productView(ProductId $id, ?ReceiptProduct $product): ReceiptLineProductView
    {
        return null === $product
            ? new ReceiptLineProductView($id->value(), null, null, false)
            : new ReceiptLineProductView($id->value(), $product->name, $product->brand, $product->available);
    }
}
