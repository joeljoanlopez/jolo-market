<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Shared\Domain\Exception\NotFound;

/**
 * Also thrown for a receipt of another list, so ids of other lists reveal nothing (PD-15).
 */
final class ReceiptNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Receipt "%s" was not found.', $id));
    }
}
