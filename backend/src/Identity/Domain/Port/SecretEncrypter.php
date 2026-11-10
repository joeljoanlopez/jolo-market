<?php

declare(strict_types=1);

namespace App\Identity\Domain\Port;

use App\Identity\Domain\ValueObject\EncryptedTotpSecret;

/**
 * Authenticated encryption for TOTP secrets at rest.
 */
interface SecretEncrypter
{
    public function encrypt(#[\SensitiveParameter] string $secret): EncryptedTotpSecret;

    public function decrypt(EncryptedTotpSecret $encrypted): string;
}
