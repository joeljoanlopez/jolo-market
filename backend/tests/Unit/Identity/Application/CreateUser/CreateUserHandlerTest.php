<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\CreateUser;

use App\Identity\Application\AdminActor;
use App\Identity\Application\AdminRequired;
use App\Identity\Application\CreateUser\CreateUserCommand;
use App\Identity\Application\CreateUser\CreateUserHandler;
use App\Identity\Application\CreateUser\UsernameAlreadyTaken;
use App\Identity\Domain\Model\InvalidRole;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\InvalidUsername;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\SequentialActivationTokenGenerator;
use App\Tests\Support\Identity\Users;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateUserHandlerTest extends TestCase
{
    private const string ADMIN_ID = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string USER_ID = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string NEW_ID = '00000000-0000-4000-8000-000000000001';

    private InMemoryUserRepository $users;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->users->save(Users::active(self::ADMIN_ID, 'admin', Role::ADMIN));
        $this->users->save(Users::active(self::USER_ID, 'maria', Role::USER));
    }

    #[Test]
    public function an_admin_creates_a_user_pending_activation(): void
    {
        $created = ($this->handler())(new CreateUserCommand(self::ADMIN_ID, 'Pedro', 'USER'));

        $user = $this->users->findById(UserId::fromString(self::NEW_ID)) ?? self::fail('The user was not saved.');
        self::assertSame('pedro', $user->username()->value());
        self::assertSame(Role::USER, $user->role());
        self::assertSame(UserStatus::PENDING_ACTIVATION, $user->status());
        self::assertSame(self::ADMIN_ID, $user->createdBy()?->value());
        self::assertEquals(Users::at('2026-11-09 12:00:00'), $user->createdAt());
        self::assertSame(self::NEW_ID, $created->userId);
        self::assertSame('pedro', $created->username);
    }

    #[Test]
    public function it_returns_the_plain_activation_token_once_and_stores_only_its_hash(): void
    {
        $created = ($this->handler())(new CreateUserCommand(self::ADMIN_ID, 'pedro', 'USER'));

        self::assertSame(SequentialActivationTokenGenerator::nth(1), $created->activationToken);
        self::assertEquals(Users::at('2026-11-12 12:00:00'), $created->activationExpiresAt);
        $user = $this->users->findById(UserId::fromString(self::NEW_ID)) ?? self::fail('The user was not saved.');
        self::assertSame(ActivationToken::hashOf($created->activationToken), $user->activation()?->hash());
    }

    #[Test]
    public function an_admin_can_create_another_admin(): void
    {
        ($this->handler())(new CreateUserCommand(self::ADMIN_ID, 'pedro', 'ADMIN'));

        self::assertSame(Role::ADMIN, $this->users->findById(UserId::fromString(self::NEW_ID))?->role());
    }

    #[Test]
    public function a_user_who_is_not_an_admin_cannot_create_users(): void
    {
        $this->expectException(AdminRequired::class);

        ($this->handler())(new CreateUserCommand(self::USER_ID, 'pedro', 'USER'));
    }

    #[Test]
    public function an_unknown_actor_cannot_create_users(): void
    {
        $this->expectException(AdminRequired::class);

        ($this->handler())(new CreateUserCommand('0192f3a4-7b1c-7d2e-8f30-000000000099', 'pedro', 'USER'));
    }

    #[Test]
    public function an_admin_who_is_not_active_yet_cannot_create_users(): void
    {
        $pendingAdminId = '0192f3a4-7b1c-7d2e-8f30-000000000003';
        $this->users->save(Users::pending($pendingAdminId, 'newadmin', SequentialActivationTokenGenerator::nth(99), Role::ADMIN));

        $this->expectException(AdminRequired::class);

        ($this->handler())(new CreateUserCommand($pendingAdminId, 'pedro', 'USER'));
    }

    #[Test]
    public function it_refuses_a_username_already_taken_whatever_its_case(): void
    {
        $this->expectException(UsernameAlreadyTaken::class);

        ($this->handler())(new CreateUserCommand(self::ADMIN_ID, 'MARIA', 'USER'));
    }

    #[Test]
    public function it_refuses_an_invalid_username(): void
    {
        $this->expectException(InvalidUsername::class);

        ($this->handler())(new CreateUserCommand(self::ADMIN_ID, 'm', 'USER'));
    }

    #[Test]
    public function it_refuses_an_unknown_role(): void
    {
        $this->expectException(InvalidRole::class);

        ($this->handler())(new CreateUserCommand(self::ADMIN_ID, 'pedro', 'OWNER'));
    }

    #[Test]
    public function it_saves_nothing_when_it_fails(): void
    {
        try {
            ($this->handler())(new CreateUserCommand(self::USER_ID, 'pedro', 'USER'));
        } catch (AdminRequired) {
        }

        self::assertNull($this->users->findByUsername(Username::fromString('pedro')));
    }

    private function handler(): CreateUserHandler
    {
        return new CreateUserHandler(
            $this->users,
            new AdminActor($this->users),
            new SequentialActivationTokenGenerator(),
            new SequentialIdGenerator(),
            FixedClock::at('2026-11-09 12:00:00'),
        );
    }
}
