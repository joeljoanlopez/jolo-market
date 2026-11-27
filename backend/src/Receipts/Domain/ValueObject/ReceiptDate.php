<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

/**
 * The day a receipt was paid, in Europe/Madrid (PD-66). Whether it is in the future depends on
 * the clock, so the receipt checks that where the date is set.
 */
final readonly class ReceiptDate
{
    public const string TIMEZONE = 'Europe/Madrid';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone(self::TIMEZONE));
        if (false === $day || $day->format('Y-m-d') !== $value) {
            throw InvalidReceiptDate::malformed($value);
        }

        return new self($value);
    }

    public static function today(\DateTimeImmutable $now): self
    {
        return new self($now->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d'));
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * "YYYY-MM", the month its spending counts in (PD-70).
     */
    public function month(): string
    {
        return substr($this->value, 0, 7);
    }

    public function isAfter(self $other): bool
    {
        // ISO days compare correctly as strings.
        return $this->value > $other->value;
    }
}
