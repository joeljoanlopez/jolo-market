<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\Infrastructure\Security;

use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Identity\Domain\ValueObject\Username;
use App\Identity\Infrastructure\Security\InvalidEncryptionKey;
use App\Identity\Infrastructure\Security\OtphpTotpService;
use App\Identity\Infrastructure\Security\RandomActivationTokenGenerator;
use App\Identity\Infrastructure\Security\SecretDecryptionFailed;
use App\Identity\Infrastructure\Security\SodiumSecretEncrypter;
use App\Identity\Infrastructure\Security\SymfonyPasswordHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

final class IdentityAdaptersTest extends TestCase
{
    /** RFC 6238 test secret "12345678901234567890" in base32. */
    private const string RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    #[Test]
    public function the_password_hasher_stores_a_salted_hash_it_can_verify(): void
    {
        $hasher = new SymfonyPasswordHasher(new NativePasswordHasher(cost: 4));

        $hash = $hasher->hash(PlainPassword::fromString('Correct-horse-1'));

        self::assertStringNotContainsString('Correct-horse-1', $hash->value());
        self::assertNotSame($hash->value(), $hasher->hash(PlainPassword::fromString('Correct-horse-1'))->value());
        self::assertTrue($hasher->verify($hash, 'Correct-horse-1'));
        self::assertFalse($hasher->verify($hash, 'Correct-horse-2'));
    }

    #[Test]
    public function the_totp_service_accepts_the_rfc_6238_codes(): void
    {
        $totp = new OtphpTotpService();

        self::assertSame(1, $totp->matchingTimeStep(self::RFC_SECRET, TotpCode::fromString('287082'), self::at(59)));
        self::assertSame(37037036, $totp->matchingTimeStep(self::RFC_SECRET, TotpCode::fromString('081804'), self::at(1111111109)));
        self::assertNull($totp->matchingTimeStep(self::RFC_SECRET, TotpCode::fromString('081805'), self::at(1111111109)));
    }

    #[Test]
    public function the_totp_service_tolerates_fifteen_seconds_of_clock_drift(): void
    {
        $totp = new OtphpTotpService();

        // 081804 is the code of the window 1111111080 to 1111111109.
        self::assertSame(37037036, $totp->matchingTimeStep(self::RFC_SECRET, TotpCode::fromString('081804'), self::at(1111111124)));
        self::assertNull($totp->matchingTimeStep(self::RFC_SECRET, TotpCode::fromString('081804'), self::at(1111111125)));
        self::assertSame(37037036, $totp->matchingTimeStep(self::RFC_SECRET, TotpCode::fromString('081804'), self::at(1111111065)));
        self::assertNull($totp->matchingTimeStep(self::RFC_SECRET, TotpCode::fromString('081804'), self::at(1111111064)));
    }

    #[Test]
    public function the_totp_service_generates_base32_secrets_of_160_bits(): void
    {
        $totp = new OtphpTotpService();

        $secret = $totp->generateSecret();

        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        self::assertNotSame($secret, $totp->generateSecret());
    }

    #[Test]
    public function the_totp_service_builds_the_uri_for_authenticator_apps(): void
    {
        $uri = (new OtphpTotpService())->provisioningUri(Username::fromString('maria'), self::RFC_SECRET);

        self::assertSame('otpauth://totp/jolo-market%3Amaria?issuer=jolo-market&secret='.self::RFC_SECRET, $uri);
    }

    #[Test]
    public function the_encrypter_round_trips_a_secret_without_storing_it_in_clear(): void
    {
        $encrypter = new SodiumSecretEncrypter(self::key());

        $encrypted = $encrypter->encrypt(self::RFC_SECRET);

        self::assertStringNotContainsString(self::RFC_SECRET, $encrypted->value());
        self::assertNotSame($encrypted->value(), $encrypter->encrypt(self::RFC_SECRET)->value());
        self::assertSame(self::RFC_SECRET, $encrypter->decrypt($encrypted));
        self::assertLessThanOrEqual(255, \strlen($encrypted->value()));
    }

    #[Test]
    public function the_encrypter_refuses_a_tampered_secret(): void
    {
        $encrypter = new SodiumSecretEncrypter(self::key());
        $raw = (string) base64_decode($encrypter->encrypt(self::RFC_SECRET)->value(), true);
        $raw[\strlen($raw) - 1] = \chr(\ord($raw[\strlen($raw) - 1]) ^ 1);

        $this->expectException(SecretDecryptionFailed::class);

        $encrypter->decrypt(EncryptedTotpSecret::fromString(base64_encode($raw)));
    }

    #[Test]
    public function the_encrypter_cannot_read_a_secret_encrypted_with_another_key(): void
    {
        $encrypted = (new SodiumSecretEncrypter(self::key()))->encrypt(self::RFC_SECRET);

        $this->expectException(SecretDecryptionFailed::class);

        (new SodiumSecretEncrypter(base64_encode(str_repeat('k', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES))))->decrypt($encrypted);
    }

    #[Test]
    public function the_encrypter_needs_a_32_byte_base64_key(): void
    {
        $this->expectException(InvalidEncryptionKey::class);

        new SodiumSecretEncrypter(base64_encode('too short'));
    }

    #[Test]
    public function the_encrypter_refuses_an_empty_key(): void
    {
        $this->expectException(InvalidEncryptionKey::class);

        new SodiumSecretEncrypter('');
    }

    #[Test]
    public function activation_tokens_are_long_random_and_url_safe(): void
    {
        $generator = new RandomActivationTokenGenerator();

        $token = $generator->generate();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
        self::assertNotSame($token, $generator->generate());
    }

    private static function at(int $timestamp): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@'.$timestamp);
    }

    private static function key(): string
    {
        return base64_encode(str_repeat('a', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }
}
