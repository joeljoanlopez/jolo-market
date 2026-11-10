<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\Model;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserStateConflict;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserManagementTest extends TestCase
{
    private const string ID = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string OLD_TOKEN = 'activation-token-0000000000000000000001';
    private const string NEW_TOKEN = 'activation-token-0000000000000000000002';

    #[Test]
    public function a_new_user_starts_at_session_version_one(): void
    {
        self::assertSame(1, self::pending()->sessionVersion());
    }

    #[Test]
    public function a_pending_user_gets_a_new_activation_link_and_the_old_one_stops_working(): void
    {
        $user = self::pending();

        $user->regenerateActivation(ActivationToken::issue(self::NEW_TOKEN, Users::at('2026-11-11 08:00:00')));

        self::assertTrue($user->activation()?->matches(self::NEW_TOKEN));
        self::assertFalse($user->activation()->matches(self::OLD_TOKEN));
        self::assertEquals(Users::at('2026-11-14 08:00:00'), $user->activation()->expiresAt());
    }

    #[Test]
    public function regenerating_the_link_drops_a_half_finished_activation(): void
    {
        $user = self::pending();
        $now = Users::at('2026-11-10 08:00:00');
        $user->setInitialPassword(self::OLD_TOKEN, PasswordHash::fromString('hash-1'), $now);
        $user->startTotpEnrollment(self::OLD_TOKEN, EncryptedTotpSecret::fromString('cipher-1'), $now);

        $user->regenerateActivation(ActivationToken::issue(self::NEW_TOKEN, $now));

        self::assertNull($user->passwordHash());
        self::assertNull($user->pendingTotpSecret());
    }

    #[Test]
    public function an_active_user_has_no_link_to_regenerate(): void
    {
        $this->expectException(UserStateConflict::class);

        self::active()->regenerateActivation(ActivationToken::issue(self::NEW_TOKEN, Users::at('2026-11-11 08:00:00')));
    }

    #[Test]
    public function a_reset_sends_an_active_user_back_to_activation_and_ends_their_sessions(): void
    {
        $user = self::active();

        $user->reset(ActivationToken::issue(self::NEW_TOKEN, Users::at('2026-11-11 08:00:00')));

        self::assertSame(UserStatus::PENDING_ACTIVATION, $user->status());
        self::assertNull($user->passwordHash());
        self::assertNull($user->totpSecret());
        self::assertNull($user->totpConfirmedAt());
        self::assertNull($user->pendingTotpSecret());
        self::assertTrue($user->activation()?->matches(self::NEW_TOKEN));
        self::assertSame(2, $user->sessionVersion());
    }

    #[Test]
    public function a_reset_user_activates_again_through_the_new_link(): void
    {
        $user = self::active();
        $user->reset(ActivationToken::issue(self::NEW_TOKEN, Users::at('2026-11-11 08:00:00')));
        $now = Users::at('2026-11-11 09:00:00');

        $user->setInitialPassword(self::NEW_TOKEN, PasswordHash::fromString('hash-2'), $now);
        $user->startTotpEnrollment(self::NEW_TOKEN, EncryptedTotpSecret::fromString('cipher-2'), $now);
        $user->confirmTotp(self::NEW_TOKEN, $now);

        self::assertTrue($user->isActive());
        self::assertSame('cipher-2', $user->totpSecret()?->value());
    }

    #[Test]
    public function a_disabled_user_must_be_enabled_before_a_reset(): void
    {
        $user = self::active();
        $user->disable();

        $this->expectException(UserStateConflict::class);
        $user->reset(ActivationToken::issue(self::NEW_TOKEN, Users::at('2026-11-11 08:00:00')));
    }

    #[Test]
    public function disabling_a_user_ends_their_sessions_and_enabling_makes_them_active_again(): void
    {
        $user = self::active();

        $user->disable();
        self::assertSame(UserStatus::DISABLED, $user->status());
        self::assertFalse($user->isActive());
        self::assertSame(2, $user->sessionVersion());

        $user->enable();
        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertSame('hashed:'.Users::PASSWORD, $user->passwordHash()?->value());
    }

    #[Test]
    public function only_an_active_user_can_be_disabled(): void
    {
        $this->expectException(UserStateConflict::class);

        self::pending()->disable();
    }

    #[Test]
    public function only_a_disabled_user_can_be_enabled(): void
    {
        $this->expectException(UserStateConflict::class);

        self::active()->enable();
    }

    #[Test]
    public function its_role_can_change(): void
    {
        $user = self::active();

        $user->changeRole(Role::ADMIN);

        self::assertSame(Role::ADMIN, $user->role());
    }

    #[Test]
    public function changing_the_password_ends_the_other_sessions(): void
    {
        $user = self::active();

        $user->changePassword(PasswordHash::fromString('hash-2'));

        self::assertSame('hash-2', $user->passwordHash()?->value());
        self::assertSame(2, $user->sessionVersion());
    }

    #[Test]
    public function only_an_active_user_changes_their_password(): void
    {
        $this->expectException(UserStateConflict::class);

        self::pending()->changePassword(PasswordHash::fromString('hash-2'));
    }

    #[Test]
    public function the_old_totp_device_keeps_working_until_the_new_one_is_confirmed(): void
    {
        $user = self::active();

        $user->startTotpReEnrollment(EncryptedTotpSecret::fromString('cipher-new'));
        self::assertSame('encrypted:'.Users::TOTP_SECRET, $user->totpSecret()?->value());
        self::assertSame('cipher-new', $user->pendingTotpSecret()?->value());
        self::assertSame(1, $user->sessionVersion());

        $user->confirmTotpReEnrollment(Users::at('2026-11-11 08:00:00'));
        self::assertSame('cipher-new', $user->totpSecret()?->value());
        self::assertNull($user->pendingTotpSecret());
        self::assertEquals(Users::at('2026-11-11 08:00:00'), $user->totpConfirmedAt());
        self::assertSame(2, $user->sessionVersion());
    }

    #[Test]
    public function a_re_enrollment_must_be_started_before_it_is_confirmed(): void
    {
        $this->expectException(UserStateConflict::class);

        self::active()->confirmTotpReEnrollment(Users::at('2026-11-11 08:00:00'));
    }

    #[Test]
    public function only_an_active_user_re_enrolls_totp(): void
    {
        $this->expectException(UserStateConflict::class);

        self::pending()->startTotpReEnrollment(EncryptedTotpSecret::fromString('cipher-new'));
    }

    #[Test]
    public function a_totp_time_step_can_be_used_only_once(): void
    {
        $user = self::active();

        self::assertTrue($user->claimTotpStep(59000000));
        self::assertSame(59000000, $user->lastTotpStep());
        self::assertFalse($user->claimTotpStep(59000000));
        self::assertFalse($user->claimTotpStep(58999999));
        self::assertTrue($user->claimTotpStep(59000001));
    }

    private static function pending(): User
    {
        return Users::pending(self::ID, 'maria', self::OLD_TOKEN);
    }

    private static function active(): User
    {
        return Users::active(self::ID, 'maria');
    }
}
