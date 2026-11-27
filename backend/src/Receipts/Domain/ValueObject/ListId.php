<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * The shopping list a receipt belongs to (PD-63). Receipts know a list only by its id.
 */
final readonly class ListId
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
