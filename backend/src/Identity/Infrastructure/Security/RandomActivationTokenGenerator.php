<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Port\ActivationTokenGenerator;

/**
 * 256 random bits, base64url without padding (43 characters).
 */
final readonly class RandomActivationTokenGenerator implements ActivationTokenGenerator
{
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
