<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\ValueObject;

/**
 * The secret of a list's join link (PD-2): base64url without padding. 22 characters hold
 * 128 bits, the least randomness a generated token may have; the generator makes longer ones.
 * It is used only to join; the list is then reached by id.
 */
final readonly class ShareToken
{
    private const string PATTERN = '/^[A-Za-z0-9_-]{22,64}$/D';

    private function __construct(private string $value)
    {
    }

    public static function fromString(#[\SensitiveParameter] string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidShareToken::malformed();
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
