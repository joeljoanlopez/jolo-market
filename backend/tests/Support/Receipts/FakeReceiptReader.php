<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Application\ReadReceipt\ReadReceiptData;
use App\Receipts\Application\ReadReceipt\ReceiptReader;
use App\Receipts\Domain\ValueObject\PhotoType;
use App\Receipts\Domain\ValueObject\SupermarketId;

/**
 * Answers what the test says; fails (null) until then.
 */
final class FakeReceiptReader implements ReceiptReader
{
    private ?ReadReceiptData $result = null;

    /** @var list<array{string, PhotoType, string}> photo, type and supermarket of every read */
    private array $reads = [];

    public function willRead(?ReadReceiptData $result): void
    {
        $this->result = $result;
    }

    public function read(string $photo, PhotoType $photoType, SupermarketId $supermarketId): ?ReadReceiptData
    {
        $this->reads[] = [$photo, $photoType, $supermarketId->value()];

        return $this->result;
    }

    /**
     * @return list<array{string, PhotoType, string}>
     */
    public function reads(): array
    {
        return $this->reads;
    }
}
