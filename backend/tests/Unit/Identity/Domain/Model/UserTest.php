<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\Model;

use App\Identity\Domain\Model\ActivationLinkNotValid;
use App\Identity\Domain\Model\ActivationStepOutOfOrder;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private const string USER_ID = '00000000-0000-4000-8000-000000000002';
    private const string ADMIN_ID = '00000000-0000-4000-8000-000000000001';
    private const string TOKEN = 'Zx9vQm3kT8wLr2Nf6Yb1Hc4Pj7Sd0Ga5Ue8Wq2Ki3Mo';
    private const string CREATED_AT = '2026-11-09 10:00:00';

    #[Test]
    public function it_is_created_pending_activation_by_an_admin(): void
    {
        $user = self::pendingUser();

        self::assertSame(self::USER_ID, $user->id()->value());
        self::assertSame('maria', $user->username()->value());
        self::assertSame(Role::USER, $user->role());
        self::assertSame(UserStatus::PENDING_ACTIVATION, $user->status());
        self::assertNull($user->passwordHash());
        self::assertNull($user->totpSecret());
        self::assertTrue($user->activation()?->matches(self::TOKEN));
        self::assertEquals(self::at(self::CREATED_AT), $user->createdAt());
        self::assertSame(self::ADMIN_ID, $user->createdBy()?->value());
        self::assertFalse($user->isActive());
    }

    #[Test]
    public function it_has_no_creator_when_it_is_the_first_admin(): void
    {
        $user = User::createPending(
            UserId::fromString(self::ADMIN_ID),
            Username::fromString('admin'),
            Role::ADMIN,
            ActivationToken::issue(self::TOKEN, self::at(self::CREATED_AT)),
            self::at(self::CREATED_AT),
            null,
        );

        self::assertNull($user->createdBy());
        self::assertTrue($user->isAdmin());
    }

    #[Test]
    public function it_becomes_active_after_setting_a_password_and_confirming_totp(): void
    {
        $user = self::pendingUser();
        $now = self::at('2026-11-10 09:00:00');

        $user->setInitialPassword(self::TOKEN, PasswordHash::fromString('hash-1'), $now);
        $user->startTotpEnrollment(self::TOKEN, EncryptedTotpSecret::fromString('cipher-1'), $now);
        $user->confirmTotp(self::TOKEN, $now);

        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertTrue($user->isActive());
        self::assertSame('hash-1', $user->passwordHash()?->value());
        self::assertSame('cipher-1', $user->totpSecret()?->value());
        self::assertNull($user->pendingTotpSecret());
        self::assertEquals($now, $user->totpConfirmedAt());
    }

    #[Test]
    public function its_activation_link_is_used_up_once_the_user_is_active(): void
    {
        $user = self::activeUser();

        self::assertNull($user->activation());

        $this->expectException(ActivationLinkNotValid::class);
        $user->setInitialPassword(self::TOKEN, PasswordHash::fromString('hash-2'), self::at('2026-11-10 09:05:00'));
    }

    #[Test]
    public function it_refuses_a_different_activation_token(): void
    {
        $user = self::pendingUser();

        $this->expectException(ActivationLinkNotValid::class);
        $user->setInitialPassword('another-token-another-token-another-token', PasswordHash::fromString('hash-1'), self::at('2026-11-10 09:00:00'));
    }

    #[Test]
    public function it_refuses_an_expired_activation_token(): void
    {
        $user = self::pendingUser();

        $this->expectException(ActivationLinkNotValid::class);
        $user->setInitialPassword(self::TOKEN, PasswordHash::fromString('hash-1'), self::at('2026-11-12 10:00:00'));
    }

    #[Test]
    public function an_expired_token_cannot_finish_an_activation_already_started(): void
    {
        $user = self::pendingUser();
        $user->setInitialPassword(self::TOKEN, PasswordHash::fromString('hash-1'), self::at('2026-11-12 09:00:00'));
        $user->startTotpEnrollment(self::TOKEN, EncryptedTotpSecret::fromString('cipher-1'), self::at('2026-11-12 09:00:00'));

        $this->expectException(ActivationLinkNotValid::class);
        $user->confirmTotp(self::TOKEN, self::at('2026-11-12 10:00:00'));
    }

    #[Test]
    public function it_needs_a_password_before_enrolling_totp(): void
    {
        $user = self::pendingUser();

        $this->expectException(ActivationStepOutOfOrder::class);
        $user->startTotpEnrollment(self::TOKEN, EncryptedTotpSecret::fromString('cipher-1'), self::at('2026-11-10 09:00:00'));
    }

    #[Test]
    public function it_needs_a_totp_enrollment_before_confirming_it(): void
    {
        $user = self::pendingUser();
        $user->setInitialPassword(self::TOKEN, PasswordHash::fromString('hash-1'), self::at('2026-11-10 09:00:00'));

        $this->expectException(ActivationStepOutOfOrder::class);
        $user->confirmTotp(self::TOKEN, self::at('2026-11-10 09:00:00'));
    }

    #[Test]
    public function a_new_totp_enrollment_replaces_the_previous_one_before_confirmation(): void
    {
        $user = self::pendingUser();
        $now = self::at('2026-11-10 09:00:00');
        $user->setInitialPassword(self::TOKEN, PasswordHash::fromString('hash-1'), $now);
        $user->startTotpEnrollment(self::TOKEN, EncryptedTotpSecret::fromString('cipher-1'), $now);

        $user->startTotpEnrollment(self::TOKEN, EncryptedTotpSecret::fromString('cipher-2'), $now);

        self::assertSame('cipher-2', $user->pendingTotpSecret()?->value());
        $user->confirmTotp(self::TOKEN, $now);
        self::assertSame('cipher-2', $user->totpSecret()?->value());
    }

    #[Test]
    public function it_is_rebuilt_from_its_stored_state(): void
    {
        $user = User::reconstitute(
            UserId::fromString(self::USER_ID),
            Username::fromString('maria'),
            Role::ADMIN,
            UserStatus::ACTIVE,
            PasswordHash::fromString('hash-1'),
            EncryptedTotpSecret::fromString('cipher-1'),
            self::at('2026-11-10 09:00:00'),
            null,
            null,
            self::at(self::CREATED_AT),
            UserId::fromString(self::ADMIN_ID),
        );

        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertSame(Role::ADMIN, $user->role());
        self::assertSame('cipher-1', $user->totpSecret()?->value());
        self::assertEquals(self::at('2026-11-10 09:00:00'), $user->totpConfirmedAt());
    }

    private static function pendingUser(): User
    {
        return User::createPending(
            UserId::fromString(self::USER_ID),
            Username::fromString('maria'),
            Role::USER,
            ActivationToken::issue(self::TOKEN, self::at(self::CREATED_AT)),
            self::at(self::CREATED_AT),
            UserId::fromString(self::ADMIN_ID),
        );
    }

    private static function activeUser(): User
    {
        $user = self::pendingUser();
        $now = self::at('2026-11-10 09:00:00');
        $user->setInitialPassword(self::TOKEN, PasswordHash::fromString('hash-1'), $now);
        $user->startTotpEnrollment(self::TOKEN, EncryptedTotpSecret::fromString('cipher-1'), $now);
        $user->confirmTotp(self::TOKEN, $now);

        return $user;
    }

    private static function at(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
    }
}
