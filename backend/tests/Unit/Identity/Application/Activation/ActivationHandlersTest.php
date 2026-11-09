<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\Activation;

use App\Identity\Application\Activation\ActivationLinks;
use App\Identity\Application\Activation\ConfirmTotpEnrollmentCommand;
use App\Identity\Application\Activation\ConfirmTotpEnrollmentHandler;
use App\Identity\Application\Activation\SetInitialPasswordCommand;
use App\Identity\Application\Activation\SetInitialPasswordHandler;
use App\Identity\Application\Activation\TotpCodeRejected;
use App\Identity\Application\Activation\ValidateActivationTokenHandler;
use App\Identity\Application\Activation\ValidateActivationTokenQuery;
use App\Identity\Domain\Model\ActivationLinkNotValid;
use App\Identity\Domain\Model\ActivationStepOutOfOrder;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\InvalidTotpCode;
use App\Identity\Domain\ValueObject\WeakPassword;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\Identity\FakePasswordHasher;
use App\Tests\Support\Identity\FakeSecretEncrypter;
use App\Tests\Support\Identity\FakeTotpService;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivationHandlersTest extends TestCase
{
    private const string USER_ID = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string TOKEN = 'activation-token-0000000000000000000042';
    private const string PASSWORD = 'Correct-horse-1';

    private InMemoryUserRepository $users;
    private FakeTotpService $totp;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->users->save(Users::pending(self::USER_ID, 'maria', self::TOKEN));
        $this->totp = new FakeTotpService();
        $this->clock = FixedClock::at('2026-11-10 09:00:00');
    }

    #[Test]
    public function a_valid_link_tells_whose_account_it_activates(): void
    {
        $link = ($this->validate())(new ValidateActivationTokenQuery(self::TOKEN));

        self::assertSame('maria', $link->username);
        self::assertEquals(Users::at('2026-11-12 10:00:00'), $link->expiresAt);
    }

    #[Test]
    public function an_unknown_link_is_not_valid(): void
    {
        $this->expectException(ActivationLinkNotValid::class);

        ($this->validate())(new ValidateActivationTokenQuery('activation-token-0000000000000000000099'));
    }

    #[Test]
    public function an_expired_link_is_not_valid(): void
    {
        $this->clock = FixedClock::at('2026-11-12 10:00:00');

        $this->expectException(ActivationLinkNotValid::class);

        ($this->validate())(new ValidateActivationTokenQuery(self::TOKEN));
    }

    #[Test]
    public function a_used_link_is_not_valid(): void
    {
        $this->activate();

        $this->expectException(ActivationLinkNotValid::class);

        ($this->validate())(new ValidateActivationTokenQuery(self::TOKEN));
    }

    #[Test]
    public function setting_the_password_stores_its_hash_and_starts_the_totp_enrollment(): void
    {
        $enrollment = ($this->setPassword())(new SetInitialPasswordCommand(self::TOKEN, self::PASSWORD));

        self::assertSame('SECRET1', $enrollment->secret);
        self::assertSame('otpauth://totp/jolo-market:maria?secret=SECRET1&issuer=jolo-market', $enrollment->provisioningUri);
        $user = $this->storedUser();
        self::assertSame('hashed:'.self::PASSWORD, $user->passwordHash()?->value());
        self::assertSame('encrypted:SECRET1', $user->pendingTotpSecret()?->value());
        self::assertSame(UserStatus::PENDING_ACTIVATION, $user->status());
    }

    #[Test]
    public function a_password_that_breaks_the_policy_is_refused_and_nothing_is_stored(): void
    {
        try {
            ($this->setPassword())(new SetInitialPasswordCommand(self::TOKEN, 'short'));
            self::fail('The password should be refused.');
        } catch (WeakPassword) {
        }

        self::assertNull($this->storedUser()->passwordHash());
    }

    #[Test]
    public function the_password_cannot_be_set_through_an_unknown_link(): void
    {
        $this->expectException(ActivationLinkNotValid::class);

        ($this->setPassword())(new SetInitialPasswordCommand('activation-token-0000000000000000000099', self::PASSWORD));
    }

    #[Test]
    public function a_correct_code_activates_the_user_and_uses_up_the_link(): void
    {
        ($this->setPassword())(new SetInitialPasswordCommand(self::TOKEN, self::PASSWORD));
        $this->totp->acceptCode('SECRET1', '123456');

        $activated = ($this->confirm())(new ConfirmTotpEnrollmentCommand(self::TOKEN, '123 456'));

        self::assertSame(self::USER_ID, $activated->userId);
        $user = $this->storedUser();
        self::assertSame(UserStatus::ACTIVE, $user->status());
        self::assertSame('encrypted:SECRET1', $user->totpSecret()?->value());
        self::assertNull($user->activation());
    }

    #[Test]
    public function a_wrong_code_is_rejected_and_the_user_stays_pending(): void
    {
        ($this->setPassword())(new SetInitialPasswordCommand(self::TOKEN, self::PASSWORD));
        $this->totp->acceptCode('SECRET1', '123456');

        try {
            ($this->confirm())(new ConfirmTotpEnrollmentCommand(self::TOKEN, '654321'));
            self::fail('The code should be rejected.');
        } catch (TotpCodeRejected) {
        }

        self::assertSame(UserStatus::PENDING_ACTIVATION, $this->storedUser()->status());
    }

    #[Test]
    public function a_code_that_is_not_six_digits_is_invalid(): void
    {
        ($this->setPassword())(new SetInitialPasswordCommand(self::TOKEN, self::PASSWORD));

        $this->expectException(InvalidTotpCode::class);

        ($this->confirm())(new ConfirmTotpEnrollmentCommand(self::TOKEN, '12'));
    }

    #[Test]
    public function the_code_is_checked_against_the_latest_enrollment(): void
    {
        ($this->setPassword())(new SetInitialPasswordCommand(self::TOKEN, self::PASSWORD));
        ($this->setPassword())(new SetInitialPasswordCommand(self::TOKEN, self::PASSWORD));
        $this->totp->acceptCode('SECRET1', '111111');
        $this->totp->acceptCode('SECRET2', '222222');

        try {
            ($this->confirm())(new ConfirmTotpEnrollmentCommand(self::TOKEN, '111111'));
            self::fail('The code of the replaced secret should be rejected.');
        } catch (TotpCodeRejected) {
        }
        ($this->confirm())(new ConfirmTotpEnrollmentCommand(self::TOKEN, '222222'));

        self::assertSame('encrypted:SECRET2', $this->storedUser()->totpSecret()?->value());
    }

    #[Test]
    public function the_code_cannot_be_confirmed_before_the_password_is_set(): void
    {
        $this->expectException(ActivationStepOutOfOrder::class);

        ($this->confirm())(new ConfirmTotpEnrollmentCommand(self::TOKEN, '123456'));
    }

    private function activate(): void
    {
        ($this->setPassword())(new SetInitialPasswordCommand(self::TOKEN, self::PASSWORD));
        $this->totp->acceptCode('SECRET1', '123456');
        ($this->confirm())(new ConfirmTotpEnrollmentCommand(self::TOKEN, '123456'));
    }

    private function storedUser(): User
    {
        return $this->users->findById(UserId::fromString(self::USER_ID)) ?? self::fail('The user is gone.');
    }

    private function links(): ActivationLinks
    {
        return new ActivationLinks($this->users, $this->clock);
    }

    private function validate(): ValidateActivationTokenHandler
    {
        return new ValidateActivationTokenHandler($this->links());
    }

    private function setPassword(): SetInitialPasswordHandler
    {
        return new SetInitialPasswordHandler($this->links(), $this->users, new FakePasswordHasher(), $this->totp, new FakeSecretEncrypter(), $this->clock);
    }

    private function confirm(): ConfirmTotpEnrollmentHandler
    {
        return new ConfirmTotpEnrollmentHandler($this->links(), $this->users, $this->totp, new FakeSecretEncrypter(), $this->clock);
    }
}
