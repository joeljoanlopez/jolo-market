<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * The stored side of a one-time activation link: the SHA-256 hash of the plain token and
 * its expiry. The plain token is shown once to the admin and never stored.
 *
 * A fast hash is enough because the token is long and random (unlike a password).
 */
final readonly class ActivationToken
{
    public const string LIFETIME = 'PT72H';

    private const int MIN_PLAIN_LENGTH = 32;

    private function __construct(
        private string $hash,
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public static function issue(#[\SensitiveParameter] string $plainToken, \DateTimeImmutable $now): self
    {
        if (\strlen($plainToken) < self::MIN_PLAIN_LENGTH) {
            throw InvalidActivationToken::tooShort(self::MIN_PLAIN_LENGTH);
        }

        return new self(self::hashOf($plainToken), $now->add(new \DateInterval(self::LIFETIME)));
    }

    /**
     * Rebuilds a stored token. Only persistence adapters call it.
     */
    public static function reconstitute(string $hash, \DateTimeImmutable $expiresAt): self
    {
        return new self($hash, $expiresAt);
    }

    public static function hashOf(#[\SensitiveParameter] string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function matches(#[\SensitiveParameter] string $plainToken): bool
    {
        return hash_equals($this->hash, self::hashOf($plainToken));
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
