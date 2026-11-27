<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Domain\Port\ReceiptPhotoStorage;
use App\Receipts\Domain\ValueObject\ReceiptId;

final class InMemoryReceiptPhotoStorage implements ReceiptPhotoStorage
{
    /** @var array<string, string> */
    private array $photos = [];

    public function store(ReceiptId $id, string $contents): void
    {
        $this->photos[$id->value()] = $contents;
    }

    public function read(ReceiptId $id): ?string
    {
        return $this->photos[$id->value()] ?? null;
    }

    public function delete(ReceiptId $id): void
    {
        unset($this->photos[$id->value()]);
    }

    public function count(): int
    {
        return \count($this->photos);
    }
}
