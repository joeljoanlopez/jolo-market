<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\PlainPassword;

/**
 * Readable, reversible "hash" so tests can see which password was stored.
 */
final class FakePasswordHasher implements PasswordHasher
{
    public function hash(PlainPassword $password): PasswordHash
    {
        return PasswordHash::fromString('hashed:'.$password->value());
    }

    public function verify(PasswordHash $hash, string $password): bool
    {
        return $hash->value() === 'hashed:'.$password;
    }
}
