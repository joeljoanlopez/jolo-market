<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Port;

use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\SupermarketId;

/**
 * "Text as printed → product" per supermarket, shared by the whole instance (PD-67). Texts are
 * compared by LineText::normalized(), so case and repeated spaces do not matter.
 */
interface ProductLinkMemory
{
    public function find(SupermarketId $supermarketId, LineText $text): ?ProductId;

    /**
     * The last one remembered for a text wins.
     */
    public function remember(SupermarketId $supermarketId, LineText $text, ProductId $productId): void;
}
