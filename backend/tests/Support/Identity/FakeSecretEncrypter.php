<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;

final class FakeSecretEncrypter implements SecretEncrypter
{
    public function encrypt(string $secret): EncryptedTotpSecret
    {
        return EncryptedTotpSecret::fromString('encrypted:'.$secret);
    }

    public function decrypt(EncryptedTotpSecret $encrypted): string
    {
        return substr($encrypted->value(), \strlen('encrypted:'));
    }
}
