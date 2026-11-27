<?php

declare(strict_types=1);

namespace App\Tests\Support\Receipts;

use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\Port\ReceiptChangedConcurrently;
use App\Receipts\Domain\Port\ReceiptRepository;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\ReceiptId;

/**
 * Keeps copies, like database rows, and checks the loaded version on save like the DBAL adapter.
 * beforeNextSave() simulates another request saving or deleting in between.
 */
final class InMemoryReceiptRepository implements ReceiptRepository
{
    /** @var array<string, Receipt> */
    private array $receipts = [];

    /** @var (callable(self): void)|null */
    private $beforeNextSave;

    /**
     * @param callable(self): void $concurrentChange
     */
    public function beforeNextSave(callable $concurrentChange): void
    {
        $this->beforeNextSave = $concurrentChange;
    }

    public function save(Receipt $receipt): void
    {
        $concurrentChange = $this->beforeNextSave;
        $this->beforeNextSave = null;
        if (null !== $concurrentChange) {
            $concurrentChange($this);
        }

        $storedVersion = ($this->receipts[$receipt->id()->value()] ?? null)?->version() ?? 0;
        if ($storedVersion !== $receipt->loadedVersion()) {
            throw ReceiptChangedConcurrently::withId($receipt->id()->value());
        }

        $copy = clone $receipt;
        $this->receipts[$receipt->id()->value()] = Receipt::reconstitute(
            $copy->id(),
            $copy->listId(),
            $copy->supermarketId(),
            $copy->uploaderId(),
            $copy->photoType(),
            $copy->uploadedAt(),
            $copy->status(),
            $copy->readBy(),
            $copy->date(),
            $copy->total(),
            $copy->lines(),
            $copy->version(),
        );
    }

    public function remove(ReceiptId $id): void
    {
        unset($this->receipts[$id->value()]);
    }

    public function findById(ReceiptId $id): ?Receipt
    {
        $receipt = $this->receipts[$id->value()] ?? null;

        return null === $receipt ? null : clone $receipt;
    }

    public function findByList(ListId $listId): array
    {
        $found = [];
        foreach ($this->receipts as $receipt) {
            if ($receipt->listId()->equals($listId)) {
                $found[] = clone $receipt;
            }
        }

        return $found;
    }

    public function removeByList(ListId $listId): void
    {
        foreach ($this->receipts as $id => $receipt) {
            if ($receipt->listId()->equals($listId)) {
                unset($this->receipts[$id]);
            }
        }
    }

    public function count(): int
    {
        return \count($this->receipts);
    }
}
