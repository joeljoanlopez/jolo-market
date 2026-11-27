<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Port;

use App\Receipts\Domain\Model\Receipt;
use App\Receipts\Domain\ValueObject\ListId;
use App\Receipts\Domain\ValueObject\ReceiptId;

interface ReceiptRepository
{
    /**
     * Inserts a new receipt, or stores the changes of a loaded one with its lines.
     *
     * @throws ReceiptChangedConcurrently when someone else saved or deleted it after it was loaded
     */
    public function save(Receipt $receipt): void;

    public function remove(ReceiptId $id): void;

    public function findById(ReceiptId $id): ?Receipt;

    /**
     * @return list<Receipt> every receipt of the list, in no particular order
     */
    public function findByList(ListId $listId): array;

    /**
     * Deletes every receipt of the list (PD-63).
     */
    public function removeByList(ListId $listId): void;
}
