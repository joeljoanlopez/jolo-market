<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Identifier of a user. It lives in the shared kernel because other contexts
 * (shopping lists) refer to users only by this id.
 */
final readonly class UserId
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
