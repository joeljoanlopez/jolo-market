<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

/**
 * The text of a receipt line as printed (PD-66): 1 to 120 characters after trimming.
 */
final readonly class LineText
{
    public const int MAX_LENGTH = 120;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ('' === $trimmed || mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidLineText::length(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * The form the link memory compares (PD-67): case and repeated whitespace do not matter.
     */
    public function normalized(): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', $this->value) ?? $this->value);
    }
}
