<?php

declare(strict_types=1);

namespace App\Receipts\Domain\Model;

use App\Shared\Domain\Exception\Conflict;

final class ReceiptAlreadyRead extends Conflict
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Receipt "%s" was already read.', $id));
    }
}
