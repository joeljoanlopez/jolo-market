<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Shared\Domain\Exception\NotFound;

final class ReceiptLineNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Receipt line "%s" was not found.', $id));
    }
}
