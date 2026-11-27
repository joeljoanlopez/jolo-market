<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Application\ReceiptProduct;
use App\Receipts\Application\ReceiptProductCatalog;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\SupermarketId;

/**
 * Its best match for a text is the first product of the supermarket whose name starts with the
 * text's first word, ignoring case: enough to tell suggested from linked products in tests.
 */
final class InMemoryReceiptProductCatalog implements ReceiptProductCatalog
{
    /** @var array<string, ReceiptProduct> */
    private array $products = [];

    public function add(string $productId, string $supermarketId, string $name, ?string $brand = null, bool $available = true): void
    {
        $this->products[$productId] = new ReceiptProduct($productId, $supermarketId, $name, $brand, $available);
    }

    public function bestMatch(SupermarketId $supermarketId, LineText $text): ?ProductId
    {
        $firstWord = explode(' ', $text->normalized())[0];
        foreach ($this->products as $product) {
            if ($product->supermarketId === $supermarketId->value() && str_starts_with(mb_strtolower($product->name), $firstWord)) {
                return ProductId::fromString($product->productId);
            }
        }

        return null;
    }

    public function findByIds(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if (isset($this->products[$id->value()])) {
                $found[$id->value()] = $this->products[$id->value()];
            }
        }

        return $found;
    }
}
