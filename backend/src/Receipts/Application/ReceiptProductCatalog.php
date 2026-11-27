<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\SupermarketId;

/**
 * Port to the Catalog context (E10.7, E4.14).
 */
interface ReceiptProductCatalog
{
    /**
     * The product of the supermarket most similar to the printed text, available or not (PD-67).
     */
    public function bestMatch(SupermarketId $supermarketId, LineText $text): ?ProductId;

    /**
     * @param list<ProductId> $ids
     *
     * @return array<string, ReceiptProduct> by product id; unknown ids are left out
     */
    public function findByIds(array $ids): array;
}
