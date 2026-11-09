<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;

/**
 * libsodium secretbox (XSalsa20-Poly1305) with the TOTP_ENCRYPTION_KEY. A stored value is
 * base64(nonce + ciphertext); a changed value or another key fails to decrypt.
 */
final readonly class SodiumSecretEncrypter implements SecretEncrypter
{
    private string $key;

    /**
     * @param string $base64Key 32 random bytes, base64-encoded
     */
    public function __construct(#[\SensitiveParameter] string $base64Key)
    {
        $key = base64_decode($base64Key, true);
        if (false === $key || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($key)) {
            throw InvalidEncryptionKey::create(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }

        $this->key = $key;
    }

    public function encrypt(#[\SensitiveParameter] string $secret): EncryptedTotpSecret
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return EncryptedTotpSecret::fromString(base64_encode($nonce.sodium_crypto_secretbox($secret, $nonce, $this->key)));
    }

    public function decrypt(EncryptedTotpSecret $encrypted): string
    {
        $raw = base64_decode($encrypted->value(), true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw SecretDecryptionFailed::create();
        }

        $secret = sodium_crypto_secretbox_open(
            substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        return false === $secret ? throw SecretDecryptionFailed::create() : $secret;
    }
}
