<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Identifier of a line of a receipt.
 */
final readonly class ReceiptLineId
{
    private function __construct(private Uuid $uuid)
    {
    }

    public static function fromString(string $value): self
    {
        return new self(Uuid::fromString($value));
    }

    public function value(): string
    {
        return $this->uuid->value();
    }

    public function equals(self $other): bool
    {
        return $this->uuid->equals($other->uuid);
    }
}
