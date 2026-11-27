<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Receipts\Domain\ValueObject\SupermarketId;

/**
 * Port to the Catalog context: which supermarkets a receipt can be uploaded for (PD-24, PD-64)
 * and their names.
 */
interface ReceiptSupermarkets
{
    public function isEnabled(SupermarketId $supermarketId): bool;

    /**
     * Enabled or not: a receipt keeps its supermarket when it is disabled later.
     *
     * @param list<SupermarketId> $supermarketIds
     *
     * @return array<string, string> name by supermarket id; unknown ids are left out
     */
    public function namesOf(array $supermarketIds): array;
}
