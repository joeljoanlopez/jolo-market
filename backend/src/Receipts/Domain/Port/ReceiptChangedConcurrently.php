<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Port;

use App\Shared\Domain\Exception\Conflict;

/**
 * Someone else saved or deleted the receipt first. The application runs the edit again on a
 * fresh copy, so a user sees it only when the receipt keeps changing under every retry.
 */
final class ReceiptChangedConcurrently extends Conflict
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Receipt "%s" changed while it was being saved. Try again.', $id));
    }
}
