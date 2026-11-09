<?php

declare(strict_types=1);

namespace App\Identity\Domain\Port;

use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\PlainPassword;

/**
 * Slow, salted password hashing. Only the hash is ever stored.
 */
interface PasswordHasher
{
    public function hash(PlainPassword $password): PasswordHash;

    /**
     * Takes the typed text as is: a login attempt must not be rejected for breaking the policy.
     */
    public function verify(PasswordHash $hash, #[\SensitiveParameter] string $password): bool;
}
