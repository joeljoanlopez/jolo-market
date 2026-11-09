<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\ManageUsers;

use App\Identity\Application\AdminActor;
use App\Identity\Application\AdminRequired;
use App\Identity\Application\ManageUsers\CannotManageYourself;
use App\Identity\Application\ManageUsers\ChangeUserRoleCommand;
use App\Identity\Application\ManageUsers\ChangeUserRoleHandler;
use App\Identity\Application\ManageUsers\DisableUserCommand;
use App\Identity\Application\ManageUsers\DisableUserHandler;
use App\Identity\Application\ManageUsers\EnableUserCommand;
use App\Identity\Application\ManageUsers\EnableUserHandler;
use App\Identity\Application\ManageUsers\LastActiveAdmin;
use App\Identity\Application\ManageUsers\ManagedUsers;
use App\Identity\Application\ManageUsers\RegenerateActivationLinkCommand;
use App\Identity\Application\ManageUsers\RegenerateActivationLinkHandler;
use App\Identity\Application\ManageUsers\ResetUserByUsernameCommand;
use App\Identity\Application\ManageUsers\ResetUserByUsernameHandler;
use App\Identity\Application\ManageUsers\ResetUserCommand;
use App\Identity\Application\ManageUsers\ResetUserHandler;
use App\Identity\Application\ManageUsers\UserNotFound;
use App\Identity\Domain\Model\InvalidRole;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserStateConflict;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\SequentialActivationTokenGenerator;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManageUsersHandlersTest extends TestCase
{
    private const string ADMIN = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000003';
    private const string UNKNOWN = '0192f3a4-7b1c-7d2e-8f30-000000000099';
    private const string PEDRO_OLD_TOKEN = 'pedro-first-token-00000000000000000000';

    private InMemoryUserRepository $users;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->users->save(Users::active(self::ADMIN, 'admin', Role::ADMIN));
        $this->users->save(Users::active(self::MARIA, 'maria'));
        $this->users->save(Users::pending(self::PEDRO, 'pedro', self::PEDRO_OLD_TOKEN));
    }

    #[Test]
    public function an_admin_regenerates_the_link_of_a_pending_user(): void
    {
        $link = ($this->regenerate())(new RegenerateActivationLinkCommand(self::ADMIN, self::PEDRO));

        self::assertSame(SequentialActivationTokenGenerator::nth(1), $link->activationToken);
        self::assertEquals(Users::at('2026-11-14 12:00:00'), $link->activationExpiresAt);
        $pedro = $this->user(self::PEDRO);
        self::assertSame(ActivationToken::hashOf($link->activationToken), $pedro->activation()?->hash());
        self::assertFalse($pedro->activation()->matches(self::PEDRO_OLD_TOKEN));
    }

    #[Test]
    public function an_active_user_has_no_link_to_regenerate(): void
    {
        $this->expectException(UserStateConflict::class);

        ($this->regenerate())(new RegenerateActivationLinkCommand(self::ADMIN, self::MARIA));
    }

    #[Test]
    public function an_admin_resets_a_user_who_lost_their_device(): void
    {
        $link = ($this->reset())(new ResetUserCommand(self::ADMIN, self::MARIA));

        $maria = $this->user(self::MARIA);
        self::assertSame(UserStatus::PENDING_ACTIVATION, $maria->status());
        self::assertSame(2, $maria->sessionVersion());
        self::assertTrue($maria->activation()?->matches($link->activationToken));
        self::assertSame('maria', $link->username);
    }

    #[Test]
    public function an_admin_cannot_reset_themselves(): void
    {
        $this->expectException(CannotManageYourself::class);

        ($this->reset())(new ResetUserCommand(self::ADMIN, self::ADMIN));
    }

    #[Test]
    public function the_operator_resets_a_user_by_username_without_an_actor(): void
    {
        $link = ($this->operatorReset())(new ResetUserByUsernameCommand('ADMIN'));

        self::assertSame('admin', $link->username);
        self::assertSame(UserStatus::PENDING_ACTIVATION, $this->user(self::ADMIN)->status());
        self::assertTrue($this->user(self::ADMIN)->activation()?->matches($link->activationToken));
    }

    #[Test]
    public function the_operator_cannot_reset_an_unknown_username(): void
    {
        $this->expectException(UserNotFound::class);

        ($this->operatorReset())(new ResetUserByUsernameCommand('nobody'));
    }

    #[Test]
    public function an_admin_disables_and_enables_a_user(): void
    {
        ($this->disable())(new DisableUserCommand(self::ADMIN, self::MARIA));
        self::assertSame(UserStatus::DISABLED, $this->user(self::MARIA)->status());
        self::assertSame(2, $this->user(self::MARIA)->sessionVersion());

        ($this->enable())(new EnableUserCommand(self::ADMIN, self::MARIA));
        self::assertSame(UserStatus::ACTIVE, $this->user(self::MARIA)->status());
    }

    #[Test]
    public function an_admin_cannot_disable_themselves(): void
    {
        $this->expectException(CannotManageYourself::class);

        ($this->disable())(new DisableUserCommand(self::ADMIN, self::ADMIN));
    }

    #[Test]
    public function an_admin_can_disable_another_admin_while_one_active_admin_remains(): void
    {
        ($this->changeRole())(new ChangeUserRoleCommand(self::ADMIN, self::MARIA, 'ADMIN'));

        ($this->disable())(new DisableUserCommand(self::ADMIN, self::MARIA));

        self::assertSame(UserStatus::DISABLED, $this->user(self::MARIA)->status());
    }

    #[Test]
    public function an_admin_changes_the_role_of_a_user(): void
    {
        ($this->changeRole())(new ChangeUserRoleCommand(self::ADMIN, self::MARIA, 'ADMIN'));

        self::assertSame(Role::ADMIN, $this->user(self::MARIA)->role());
    }

    #[Test]
    public function an_admin_can_give_up_the_role_while_another_active_admin_remains(): void
    {
        ($this->changeRole())(new ChangeUserRoleCommand(self::ADMIN, self::MARIA, 'ADMIN'));

        ($this->changeRole())(new ChangeUserRoleCommand(self::ADMIN, self::ADMIN, 'USER'));

        self::assertSame(Role::USER, $this->user(self::ADMIN)->role());
    }

    #[Test]
    public function the_last_active_admin_cannot_lose_the_admin_role(): void
    {
        $this->expectException(LastActiveAdmin::class);

        ($this->changeRole())(new ChangeUserRoleCommand(self::ADMIN, self::ADMIN, 'USER'));
    }

    #[Test]
    public function an_unknown_role_is_refused(): void
    {
        $this->expectException(InvalidRole::class);

        ($this->changeRole())(new ChangeUserRoleCommand(self::ADMIN, self::MARIA, 'OWNER'));
    }

    #[Test]
    public function an_unknown_or_malformed_target_is_not_found(): void
    {
        foreach ([self::UNKNOWN, 'not-an-id'] as $target) {
            try {
                ($this->disable())(new DisableUserCommand(self::ADMIN, $target));
                self::fail('The user should not be found.');
            } catch (UserNotFound) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_user_who_is_not_an_admin_cannot_manage_users(): void
    {
        $attempts = [
            fn () => ($this->regenerate())(new RegenerateActivationLinkCommand(self::MARIA, self::PEDRO)),
            fn () => ($this->reset())(new ResetUserCommand(self::MARIA, self::PEDRO)),
            fn () => ($this->disable())(new DisableUserCommand(self::MARIA, self::ADMIN)),
            fn () => ($this->enable())(new EnableUserCommand(self::MARIA, self::ADMIN)),
            fn () => ($this->changeRole())(new ChangeUserRoleCommand(self::MARIA, self::MARIA, 'ADMIN')),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                self::fail('A non-admin should be refused.');
            } catch (AdminRequired) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame(Role::USER, $this->user(self::MARIA)->role());
        self::assertSame(UserStatus::ACTIVE, $this->user(self::ADMIN)->status());
    }

    private function user(string $id): User
    {
        return $this->users->findById(UserId::fromString($id)) ?? self::fail('Unknown user.');
    }

    private function managed(): ManagedUsers
    {
        return new ManagedUsers($this->users, new AdminActor($this->users));
    }

    private function regenerate(): RegenerateActivationLinkHandler
    {
        return new RegenerateActivationLinkHandler($this->managed(), $this->users, new SequentialActivationTokenGenerator(), FixedClock::at('2026-11-11 12:00:00'));
    }

    private function reset(): ResetUserHandler
    {
        return new ResetUserHandler($this->managed(), $this->users, new SequentialActivationTokenGenerator(), FixedClock::at('2026-11-11 12:00:00'));
    }

    private function operatorReset(): ResetUserByUsernameHandler
    {
        return new ResetUserByUsernameHandler($this->users, new SequentialActivationTokenGenerator(), FixedClock::at('2026-11-11 12:00:00'));
    }

    private function disable(): DisableUserHandler
    {
        return new DisableUserHandler($this->managed(), $this->users);
    }

    private function enable(): EnableUserHandler
    {
        return new EnableUserHandler($this->managed(), $this->users);
    }

    private function changeRole(): ChangeUserRoleHandler
    {
        return new ChangeUserRoleHandler($this->managed(), $this->users);
    }
}
