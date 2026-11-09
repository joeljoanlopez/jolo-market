<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

final class InvalidEncryptionKey extends \RuntimeException
{
    public static function create(int $bytes): self
    {
        return new self(\sprintf('TOTP_ENCRYPTION_KEY must be %d random bytes encoded in base64.', $bytes));
    }
}
