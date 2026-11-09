<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\Profile;

use App\Identity\Application\Activation\TotpCodeRejected;
use App\Identity\Application\Profile\ChangePasswordCommand;
use App\Identity\Application\Profile\ChangePasswordHandler;
use App\Identity\Application\Profile\ConfirmTotpReEnrollmentCommand;
use App\Identity\Application\Profile\ConfirmTotpReEnrollmentHandler;
use App\Identity\Application\Profile\CredentialsRejected;
use App\Identity\Application\Profile\ProfileUsers;
use App\Identity\Application\Profile\StartTotpReEnrollmentCommand;
use App\Identity\Application\Profile\StartTotpReEnrollmentHandler;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserStateConflict;
use App\Identity\Domain\ValueObject\WeakPassword;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\Identity\FakePasswordHasher;
use App\Tests\Support\Identity\FakeSecretEncrypter;
use App\Tests\Support\Identity\FakeTotpService;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProfileHandlersTest extends TestCase
{
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string CURRENT_CODE = '111111';
    private const string NEW_PASSWORD = 'New-password-2!';

    private InMemoryUserRepository $users;
    private FakeTotpService $totp;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->users->save(Users::active(self::MARIA, 'maria'));
        $this->totp = new FakeTotpService();
        $this->totp->acceptCode(Users::TOTP_SECRET, self::CURRENT_CODE);
    }

    #[Test]
    public function a_user_changes_their_password_and_their_other_sessions_end(): void
    {
        $changed = ($this->changePassword())(new ChangePasswordCommand(self::MARIA, Users::PASSWORD, self::CURRENT_CODE, self::NEW_PASSWORD));

        self::assertSame('hashed:'.self::NEW_PASSWORD, $this->maria()->passwordHash()?->value());
        self::assertSame(2, $this->maria()->sessionVersion());
        self::assertSame(2, $changed->sessionVersion);
    }

    #[Test]
    public function changing_the_password_needs_the_current_password(): void
    {
        $this->expectException(CredentialsRejected::class);

        ($this->changePassword())(new ChangePasswordCommand(self::MARIA, 'Wrong-password-1', self::CURRENT_CODE, self::NEW_PASSWORD));
    }

    #[Test]
    public function changing_the_password_needs_a_correct_code(): void
    {
        $this->expectException(CredentialsRejected::class);

        ($this->changePassword())(new ChangePasswordCommand(self::MARIA, Users::PASSWORD, '999999', self::NEW_PASSWORD));
    }

    #[Test]
    public function the_new_password_follows_the_policy(): void
    {
        try {
            ($this->changePassword())(new ChangePasswordCommand(self::MARIA, Users::PASSWORD, self::CURRENT_CODE, 'weak'));
            self::fail('The weak password should be refused.');
        } catch (WeakPassword) {
        }

        self::assertSame('hashed:'.Users::PASSWORD, $this->maria()->passwordHash()?->value());
    }

    #[Test]
    public function a_user_re_enrolls_totp_and_the_old_device_works_until_the_new_one_is_confirmed(): void
    {
        $enrollment = ($this->startReEnrollment())(new StartTotpReEnrollmentCommand(self::MARIA, Users::PASSWORD, self::CURRENT_CODE));

        self::assertSame('SECRET1', $enrollment->secret);
        self::assertStringContainsString('secret=SECRET1', $enrollment->provisioningUri);
        self::assertSame('encrypted:'.Users::TOTP_SECRET, $this->maria()->totpSecret()?->value());

        $this->totp->acceptCode('SECRET1', '222222');
        $changed = ($this->confirmReEnrollment())(new ConfirmTotpReEnrollmentCommand(self::MARIA, '222222'));

        self::assertSame('encrypted:SECRET1', $this->maria()->totpSecret()->value());
        self::assertSame(2, $changed->sessionVersion);
    }

    #[Test]
    public function starting_a_re_enrollment_needs_the_current_password_and_code(): void
    {
        $this->expectException(CredentialsRejected::class);

        ($this->startReEnrollment())(new StartTotpReEnrollmentCommand(self::MARIA, Users::PASSWORD, '999999'));
    }

    #[Test]
    public function a_wrong_code_from_the_new_device_keeps_the_old_one(): void
    {
        ($this->startReEnrollment())(new StartTotpReEnrollmentCommand(self::MARIA, Users::PASSWORD, self::CURRENT_CODE));
        $this->totp->acceptCode('SECRET1', '222222');

        try {
            ($this->confirmReEnrollment())(new ConfirmTotpReEnrollmentCommand(self::MARIA, '333333'));
            self::fail('The code should be rejected.');
        } catch (TotpCodeRejected) {
        }

        self::assertSame('encrypted:'.Users::TOTP_SECRET, $this->maria()->totpSecret()?->value());
        self::assertSame(1, $this->maria()->sessionVersion());
    }

    #[Test]
    public function a_re_enrollment_cannot_be_confirmed_before_it_starts(): void
    {
        $this->expectException(UserStateConflict::class);

        ($this->confirmReEnrollment())(new ConfirmTotpReEnrollmentCommand(self::MARIA, self::CURRENT_CODE));
    }

    #[Test]
    public function an_unknown_user_has_no_profile(): void
    {
        $this->expectException(NotFound::class);

        ($this->changePassword())(new ChangePasswordCommand('0192f3a4-7b1c-7d2e-8f30-000000000099', Users::PASSWORD, self::CURRENT_CODE, self::NEW_PASSWORD));
    }

    private function maria(): User
    {
        return $this->users->findById(UserId::fromString(self::MARIA)) ?? self::fail('Maria is gone.');
    }

    private function profileUsers(): ProfileUsers
    {
        return new ProfileUsers($this->users, new FakePasswordHasher(), $this->totp, new FakeSecretEncrypter(), FixedClock::at('2026-11-11 12:00:00'));
    }

    private function changePassword(): ChangePasswordHandler
    {
        return new ChangePasswordHandler($this->profileUsers(), $this->users, new FakePasswordHasher());
    }

    private function startReEnrollment(): StartTotpReEnrollmentHandler
    {
        return new StartTotpReEnrollmentHandler($this->profileUsers(), $this->users, $this->totp, new FakeSecretEncrypter());
    }

    private function confirmReEnrollment(): ConfirmTotpReEnrollmentHandler
    {
        return new ConfirmTotpReEnrollmentHandler($this->profileUsers(), $this->users, FixedClock::at('2026-11-11 12:00:00'));
    }
}
