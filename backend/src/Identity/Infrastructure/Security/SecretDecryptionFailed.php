<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

/**
 * The stored secret was changed or encrypted with another TOTP_ENCRYPTION_KEY.
 */
final class SecretDecryptionFailed extends \RuntimeException
{
    public static function create(): self
    {
        return new self('A TOTP secret could not be decrypted.');
    }
}
