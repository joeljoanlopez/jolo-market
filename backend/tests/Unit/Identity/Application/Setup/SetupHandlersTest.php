<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\Setup;

use App\Identity\Application\Setup\CompleteSetupCommand;
use App\Identity\Application\Setup\CompleteSetupHandler;
use App\Identity\Application\Setup\EnsureSetupCodeHandler;
use App\Identity\Application\Setup\IsSetupRequiredHandler;
use App\Identity\Application\Setup\SetupCodeRejected;
use App\Identity\Application\Setup\SetupNotAvailable;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\InvalidUsername;
use App\Identity\Domain\ValueObject\Username;
use App\Tests\Support\FixedClock;
use App\Tests\Support\Identity\InMemorySetupCodeRepository;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\SequentialActivationTokenGenerator;
use App\Tests\Support\Identity\SequentialSetupCodeGenerator;
use App\Tests\Support\Identity\Users;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SetupHandlersTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemorySetupCodeRepository $codes;
    private SequentialSetupCodeGenerator $generator;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->codes = new InMemorySetupCodeRepository();
        $this->generator = new SequentialSetupCodeGenerator();
    }

    #[Test]
    public function setup_is_required_only_while_there_are_no_users(): void
    {
        $required = new IsSetupRequiredHandler($this->users);
        self::assertTrue($required());

        $this->users->save(Users::active('0192f3a4-7b1c-7d2e-8f30-000000000001', 'admin', Role::ADMIN));
        self::assertFalse($required());
    }

    #[Test]
    public function a_new_code_is_generated_on_each_start_while_there_are_no_users(): void
    {
        $first = ($this->ensure())();
        $second = ($this->ensure())();

        self::assertSame(SequentialSetupCodeGenerator::nth(1), $first);
        self::assertSame(SequentialSetupCodeGenerator::nth(2), $second);
        self::assertFalse($this->codes->find()?->matches($first));
        self::assertTrue($this->codes->find()->matches($second));
    }

    #[Test]
    public function no_code_is_generated_once_a_user_exists_and_a_leftover_one_is_removed(): void
    {
        ($this->ensure())();
        $this->users->save(Users::active('0192f3a4-7b1c-7d2e-8f30-000000000001', 'admin', Role::ADMIN));

        self::assertNull(($this->ensure())());
        self::assertNull($this->codes->find());
    }

    #[Test]
    public function the_right_code_creates_the_first_admin_pending_activation(): void
    {
        $code = ($this->ensure())();

        $created = ($this->complete())(new CompleteSetupCommand(strtolower(str_replace('-', ' ', (string) $code)), 'Admin'));

        $admin = $this->users->findByUsername(Username::fromString('admin')) ?? self::fail('No admin.');
        self::assertSame(Role::ADMIN, $admin->role());
        self::assertSame(UserStatus::PENDING_ACTIVATION, $admin->status());
        self::assertNull($admin->createdBy());
        self::assertSame(ActivationToken::hashOf($created->activationToken), $admin->activation()?->hash());
        self::assertSame('admin', $created->username);
    }

    #[Test]
    public function the_code_cannot_be_used_twice(): void
    {
        $code = (string) ($this->ensure())();
        ($this->complete())(new CompleteSetupCommand($code, 'admin'));

        $this->expectException(SetupNotAvailable::class);

        ($this->complete())(new CompleteSetupCommand($code, 'another'));
    }

    #[Test]
    public function a_wrong_code_is_rejected_and_creates_nobody(): void
    {
        ($this->ensure())();

        try {
            ($this->complete())(new CompleteSetupCommand('BBBB-BBBB-BBBB-BBBB', 'admin'));
            self::fail('The code should be rejected.');
        } catch (SetupCodeRejected) {
        }

        self::assertFalse($this->users->hasAnyUser());
    }

    #[Test]
    public function without_a_generated_code_any_code_is_rejected(): void
    {
        $this->expectException(SetupCodeRejected::class);

        ($this->complete())(new CompleteSetupCommand('AAAA-AAAA-AAAA-AAA1', 'admin'));
    }

    #[Test]
    public function setup_is_not_available_once_a_user_exists(): void
    {
        $code = (string) ($this->ensure())();
        $this->users->save(Users::active('0192f3a4-7b1c-7d2e-8f30-000000000001', 'someone', Role::USER));

        $this->expectException(SetupNotAvailable::class);

        ($this->complete())(new CompleteSetupCommand($code, 'admin'));
    }

    #[Test]
    public function an_invalid_username_keeps_the_code(): void
    {
        $code = (string) ($this->ensure())();

        try {
            ($this->complete())(new CompleteSetupCommand($code, 'a'));
            self::fail('The username should be refused.');
        } catch (InvalidUsername) {
        }

        self::assertTrue($this->codes->find()?->matches($code));
    }

    private function ensure(): EnsureSetupCodeHandler
    {
        return new EnsureSetupCodeHandler($this->users, $this->codes, $this->generator, FixedClock::at('2026-11-09 08:00:00'));
    }

    private function complete(): CompleteSetupHandler
    {
        return new CompleteSetupHandler($this->users, $this->codes, new SequentialActivationTokenGenerator(), new SequentialIdGenerator(), FixedClock::at('2026-11-09 09:00:00'));
    }
}
