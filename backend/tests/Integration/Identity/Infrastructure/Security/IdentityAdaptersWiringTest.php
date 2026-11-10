<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\Infrastructure\Security;

use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\ValueObject\PlainPassword;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IdentityAdaptersWiringTest extends KernelTestCase
{
    #[Test]
    public function the_secret_encrypter_uses_the_key_of_the_environment(): void
    {
        self::bootKernel();
        $encrypter = self::getContainer()->get('test.identity.secret_encrypter');
        self::assertInstanceOf(SecretEncrypter::class, $encrypter);

        self::assertSame('JBSWY3DPEHPK3PXP', $encrypter->decrypt($encrypter->encrypt('JBSWY3DPEHPK3PXP')));
    }

    #[Test]
    public function the_password_hasher_is_wired(): void
    {
        self::bootKernel();
        $hasher = self::getContainer()->get('test.identity.password_hasher');
        self::assertInstanceOf(PasswordHasher::class, $hasher);

        self::assertTrue($hasher->verify($hasher->hash(PlainPassword::fromString('Correct-horse-1')), 'Correct-horse-1'));
    }
}
