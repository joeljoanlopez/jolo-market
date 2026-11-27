<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * A catalog product a receipt line is linked to or suggested (PD-67). Receipts know a product only by its id.
 */
final readonly class ProductId
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
