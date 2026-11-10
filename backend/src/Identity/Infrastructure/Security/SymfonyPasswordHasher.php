<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\PlainPassword;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Wraps Symfony's "auto" hasher (NativePasswordHasher: bcrypt or Argon2id, salted).
 */
final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordHasherInterface $hasher)
    {
    }

    public function hash(PlainPassword $password): PasswordHash
    {
        return PasswordHash::fromString($this->hasher->hash($password->value()));
    }

    public function verify(PasswordHash $hash, #[\SensitiveParameter] string $password): bool
    {
        return $this->hasher->verify($hash->value(), $password);
    }
}
