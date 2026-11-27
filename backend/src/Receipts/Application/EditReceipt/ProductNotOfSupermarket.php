<?php

declare(strict_types=1);

namespace App\Receipts\Application\EditReceipt;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * PD-67: a line is linked only to a known product of the receipt's supermarket.
 */
final class ProductNotOfSupermarket extends InvalidArgument
{
    public static function withId(string $productId, string $supermarketId): self
    {
        return new self(\sprintf('Product "%s" is not a product of "%s".', $productId, $supermarketId));
    }
}
