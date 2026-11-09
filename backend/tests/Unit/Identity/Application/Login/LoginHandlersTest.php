<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\Login;

use App\Identity\Application\Login\FindSessionUserHandler;
use App\Identity\Application\Login\FindSessionUserQuery;
use App\Identity\Application\Login\InvalidCredentials;
use App\Identity\Application\Login\LogInCommand;
use App\Identity\Application\Login\LogInHandler;
use App\Identity\Application\Login\SessionUser;
use App\Identity\Application\Login\VerifyLoginCodeCommand;
use App\Identity\Application\Login\VerifyLoginCodeHandler;
use App\Identity\Domain\Model\Role;
use App\Tests\Support\FixedClock;
use App\Tests\Support\Identity\FakePasswordHasher;
use App\Tests\Support\Identity\FakeSecretEncrypter;
use App\Tests\Support\Identity\FakeTotpService;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoginHandlersTest extends TestCase
{
    private const string ADMIN = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000003';
    private const string DISABLED = '0192f3a4-7b1c-7d2e-8f30-000000000004';
    private const string CODE = '123456';

    private InMemoryUserRepository $users;
    private FakeTotpService $totp;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->users->save(Users::active(self::ADMIN, 'admin', Role::ADMIN));
        $this->users->save(Users::active(self::MARIA, 'maria'));
        $this->users->save(Users::pending(self::PEDRO, 'pedro', 'activation-token-0000000000000000000001'));
        $disabled = Users::active(self::DISABLED, 'lucia');
        $disabled->disable();
        $this->users->save($disabled);
        $this->totp = new FakeTotpService();
        $this->totp->acceptCode(Users::TOTP_SECRET, self::CODE);
    }

    #[Test]
    public function the_right_password_starts_a_login_that_still_needs_a_code(): void
    {
        $pending = ($this->logIn())(new LogInCommand('Maria', Users::PASSWORD));

        self::assertSame(self::MARIA, $pending->userId);
    }

    #[Test]
    #[DataProvider('rejectedLogins')]
    public function every_rejected_login_gets_the_same_error(string $username, string $password): void
    {
        try {
            ($this->logIn())(new LogInCommand($username, $password));
            self::fail('The login should be rejected.');
        } catch (InvalidCredentials $error) {
            self::assertSame('Invalid credentials.', $error->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedLogins(): iterable
    {
        yield 'wrong password' => ['maria', 'Wrong-password-1'];
        yield 'unknown username' => ['nobody', Users::PASSWORD];
        yield 'malformed username' => ['m', Users::PASSWORD];
        yield 'user pending activation' => ['pedro', Users::PASSWORD];
        yield 'disabled user' => ['lucia', Users::PASSWORD];
    }

    #[Test]
    public function a_correct_code_completes_the_login(): void
    {
        $user = ($this->verifyCode())(new VerifyLoginCodeCommand(self::ADMIN, '123 456'));

        self::assertEquals(new SessionUser(self::ADMIN, 'admin', 'ADMIN', 1), $user);
    }

    #[Test]
    public function a_code_cannot_be_used_for_a_second_login(): void
    {
        ($this->verifyCode())(new VerifyLoginCodeCommand(self::MARIA, self::CODE));

        $this->expectException(InvalidCredentials::class);

        ($this->verifyCode())(new VerifyLoginCodeCommand(self::MARIA, self::CODE));
    }

    #[Test]
    public function a_wrong_code_gets_the_same_error_as_a_wrong_password(): void
    {
        $this->expectException(InvalidCredentials::class);

        ($this->verifyCode())(new VerifyLoginCodeCommand(self::MARIA, '654321'));
    }

    #[Test]
    public function a_malformed_code_is_just_a_wrong_code(): void
    {
        $this->expectException(InvalidCredentials::class);

        ($this->verifyCode())(new VerifyLoginCodeCommand(self::MARIA, 'abc'));
    }

    #[Test]
    public function a_user_disabled_between_both_steps_cannot_finish_the_login(): void
    {
        $this->expectException(InvalidCredentials::class);

        ($this->verifyCode())(new VerifyLoginCodeCommand(self::DISABLED, self::CODE));
    }

    #[Test]
    public function a_session_finds_its_active_user(): void
    {
        $handler = new FindSessionUserHandler($this->users);

        self::assertEquals(new SessionUser(self::MARIA, 'maria', 'USER', 1), $handler(new FindSessionUserQuery(self::MARIA)));
    }

    #[Test]
    #[DataProvider('usersWithoutSession')]
    public function a_session_finds_no_user_unless_it_is_active(string $userId): void
    {
        self::assertNull((new FindSessionUserHandler($this->users))(new FindSessionUserQuery($userId)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function usersWithoutSession(): iterable
    {
        yield 'pending activation' => [self::PEDRO];
        yield 'disabled' => [self::DISABLED];
        yield 'unknown' => ['0192f3a4-7b1c-7d2e-8f30-000000000099'];
        yield 'malformed id' => ['not-an-id'];
    }

    private function logIn(): LogInHandler
    {
        return new LogInHandler($this->users, new FakePasswordHasher());
    }

    private function verifyCode(): VerifyLoginCodeHandler
    {
        return new VerifyLoginCodeHandler($this->users, $this->totp, new FakeSecretEncrypter(), FixedClock::at('2026-11-11 12:00:00'));
    }
}
