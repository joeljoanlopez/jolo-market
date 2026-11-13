<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Configuration;

use App\Identity\Infrastructure\Security\InvalidEncryptionKey;
use App\Identity\Infrastructure\Security\SodiumSecretEncrypter;
use App\Shared\Infrastructure\Configuration\ConfigurationCheck;

/**
 * TOTP_ENCRYPTION_KEY, validated by the encrypter that uses it.
 */
final readonly class IdentityConfigurationCheck implements ConfigurationCheck
{
    public function __construct(#[\SensitiveParameter] private ?string $totpEncryptionKey)
    {
    }

    public function problems(): array
    {
        if (null === $this->totpEncryptionKey || '' === $this->totpEncryptionKey) {
            return ['TOTP_ENCRYPTION_KEY is missing: generate one with php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"'];
        }

        try {
            new SodiumSecretEncrypter($this->totpEncryptionKey);
        } catch (InvalidEncryptionKey $invalid) {
            return ['TOTP_ENCRYPTION_KEY is invalid: '.$invalid->getMessage()];
        }

        return [];
    }
}
