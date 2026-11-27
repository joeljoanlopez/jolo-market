<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Port;

use App\Receipts\Domain\ValueObject\ReceiptId;

/**
 * Keeps one photo per receipt, named by the receipt id only (PD-68).
 */
interface ReceiptPhotoStorage
{
    public function store(ReceiptId $id, string $contents): void;

    /**
     * @return string|null the stored bytes, or null when there is no photo
     */
    public function read(ReceiptId $id): ?string;

    /**
     * Idempotent: deleting a missing photo is not an error.
     */
    public function delete(ReceiptId $id): void;
}
