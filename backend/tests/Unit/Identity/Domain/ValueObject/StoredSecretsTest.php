<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\InvalidStoredSecret;
use App\Identity\Domain\ValueObject\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StoredSecretsTest extends TestCase
{
    #[Test]
    public function a_password_hash_keeps_its_value(): void
    {
        self::assertSame('$argon2id$v=19$m=65536', PasswordHash::fromString('$argon2id$v=19$m=65536')->value());
    }

    #[Test]
    public function a_password_hash_cannot_be_empty(): void
    {
        $this->expectException(InvalidStoredSecret::class);

        PasswordHash::fromString('');
    }

    #[Test]
    public function an_encrypted_totp_secret_keeps_its_value(): void
    {
        self::assertSame('bm9uY2UrY2lwaGVy', EncryptedTotpSecret::fromString('bm9uY2UrY2lwaGVy')->value());
    }

    #[Test]
    public function an_encrypted_totp_secret_cannot_be_empty(): void
    {
        $this->expectException(InvalidStoredSecret::class);

        EncryptedTotpSecret::fromString('');
    }
}
