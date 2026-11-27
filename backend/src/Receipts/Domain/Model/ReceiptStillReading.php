<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Shared\Domain\Exception\Conflict;

/**
 * PD-65: the worker has not read the photo yet, so there is no draft to edit or confirm.
 */
final class ReceiptStillReading extends Conflict
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Receipt "%s" is still being read.', $id));
    }
}
