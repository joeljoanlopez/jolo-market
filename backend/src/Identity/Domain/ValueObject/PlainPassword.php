<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * A new password typed by a user, before hashing. It follows the password policy (PD-37):
 * 12 to 128 characters with an uppercase letter, a lowercase letter, a digit and a special
 * character (anything that is not a letter or a digit).
 */
final readonly class PlainPassword
{
    private const int MIN_LENGTH = 12;
    private const int MAX_LENGTH = 128;

    private function __construct(private string $value)
    {
    }

    public static function fromString(#[\SensitiveParameter] string $value): self
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw WeakPassword::notValidText();
        }

        $length = mb_strlen($value, 'UTF-8');
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw WeakPassword::wrongLength(self::MIN_LENGTH, self::MAX_LENGTH);
        }

        if (1 !== preg_match('/\p{Lu}/u', $value)
            || 1 !== preg_match('/\p{Ll}/u', $value)
            || 1 !== preg_match('/\p{Nd}/u', $value)
            || 1 !== preg_match('/[^\p{L}\p{N}]/u', $value)
        ) {
            throw WeakPassword::missingCharacterKind();
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
