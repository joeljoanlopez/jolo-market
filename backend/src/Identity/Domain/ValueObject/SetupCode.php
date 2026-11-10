<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * The stored side of the one-time first-admin setup code (PD-14): the SHA-256 hash of the code
 * without spaces or hyphens, in uppercase, so it can be typed loosely from the logs.
 */
final readonly class SetupCode
{
    private function __construct(
        private string $hash,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function issue(#[\SensitiveParameter] string $plainCode, \DateTimeImmutable $now): self
    {
        return new self(self::hashOf($plainCode), $now);
    }

    /**
     * Rebuilds a stored code. Only persistence adapters call it.
     */
    public static function reconstitute(string $hash, \DateTimeImmutable $createdAt): self
    {
        return new self($hash, $createdAt);
    }

    public function matches(#[\SensitiveParameter] string $plainCode): bool
    {
        return '' !== self::normalize($plainCode) && hash_equals($this->hash, self::hashOf($plainCode));
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function hashOf(string $plainCode): string
    {
        return hash('sha256', self::normalize($plainCode));
    }

    private static function normalize(string $plainCode): string
    {
        return strtoupper(str_replace([' ', '-'], '', $plainCode));
    }
}
