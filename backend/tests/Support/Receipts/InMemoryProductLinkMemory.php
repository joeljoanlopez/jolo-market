<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Domain\Port\ProductLinkMemory;
use App\Receipts\Domain\ValueObject\LineText;
use App\Receipts\Domain\ValueObject\ProductId;
use App\Receipts\Domain\ValueObject\SupermarketId;

final class InMemoryProductLinkMemory implements ProductLinkMemory
{
    /** @var array<string, ProductId> */
    private array $links = [];

    public function find(SupermarketId $supermarketId, LineText $text): ?ProductId
    {
        return $this->links[self::key($supermarketId, $text)] ?? null;
    }

    public function remember(SupermarketId $supermarketId, LineText $text, ProductId $productId): void
    {
        $this->links[self::key($supermarketId, $text)] = $productId;
    }

    /**
     * @return array<string, string> "supermarket|normalized text" => product id
     */
    public function all(): array
    {
        return array_map(static fn (ProductId $id): string => $id->value(), $this->links);
    }

    private static function key(SupermarketId $supermarketId, LineText $text): string
    {
        return $supermarketId->value().'|'.$text->normalized();
    }
}
