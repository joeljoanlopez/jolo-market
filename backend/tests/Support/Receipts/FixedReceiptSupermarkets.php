<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Application\ReceiptSupermarkets;
use App\Receipts\Domain\ValueObject\SupermarketId;

final readonly class FixedReceiptSupermarkets implements ReceiptSupermarkets
{
    /**
     * @param array<string, string> $names   name by id, enabled or not
     * @param list<string>          $enabled
     */
    public function __construct(private array $names, private array $enabled)
    {
    }

    public function isEnabled(SupermarketId $supermarketId): bool
    {
        return \in_array($supermarketId->value(), $this->enabled, true);
    }

    public function namesOf(array $supermarketIds): array
    {
        $found = [];
        foreach ($supermarketIds as $id) {
            if (isset($this->names[$id->value()])) {
                $found[$id->value()] = $this->names[$id->value()];
            }
        }

        return $found;
    }
}
