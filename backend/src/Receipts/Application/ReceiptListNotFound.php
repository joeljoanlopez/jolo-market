<?php

declare(strict_types=1);

namespace App\Receipts\Application;

use App\Shared\Domain\Exception\NotFound;

/**
 * The list is unknown, or the actor may not know it exists (PD-15, PD-63).
 */
final class ReceiptListNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Shopping list "%s" was not found.', $id));
    }
}
