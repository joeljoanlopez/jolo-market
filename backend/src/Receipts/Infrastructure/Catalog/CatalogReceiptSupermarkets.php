<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Catalog;

use App\Catalog\Application\ListSupermarkets\ListSupermarketsHandler;
use App\Catalog\Application\ListSupermarkets\ListSupermarketsQuery;
use App\Receipts\Application\ReceiptSupermarkets;
use App\Receipts\Domain\ValueObject\SupermarketId;

/**
 * The enabled supermarkets through Catalog's application query API (PD-24). A supermarket that is
 * disabled later has no name here, so its receipts show its id.
 */
final readonly class CatalogReceiptSupermarkets implements ReceiptSupermarkets
{
    public function __construct(private ListSupermarketsHandler $listSupermarkets)
    {
    }

    public function isEnabled(SupermarketId $supermarketId): bool
    {
        return \array_key_exists($supermarketId->value(), $this->enabled());
    }

    public function namesOf(array $supermarketIds): array
    {
        $enabled = $this->enabled();

        $names = [];
        foreach ($supermarketIds as $id) {
            if (isset($enabled[$id->value()])) {
                $names[$id->value()] = $enabled[$id->value()];
            }
        }

        return $names;
    }

    /**
     * @return array<string, string> name by id
     */
    private function enabled(): array
    {
        $names = [];
        foreach (($this->listSupermarkets)(new ListSupermarketsQuery()) as $supermarket) {
            $names[$supermarket->id] = $supermarket->name;
        }

        return $names;
    }
}
