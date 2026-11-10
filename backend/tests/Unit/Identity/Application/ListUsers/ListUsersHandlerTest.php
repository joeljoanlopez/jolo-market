<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\ListUsers;

use App\Identity\Application\AdminActor;
use App\Identity\Application\AdminRequired;
use App\Identity\Application\ListUsers\ListUsersHandler;
use App\Identity\Application\ListUsers\ListUsersQuery;
use App\Identity\Application\ListUsers\UserSummary;
use App\Identity\Domain\Model\Role;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\SequentialActivationTokenGenerator;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListUsersHandlerTest extends TestCase
{
    private const string ADMIN_ID = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string USER_ID = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string PENDING_ID = '0192f3a4-7b1c-7d2e-8f30-000000000003';

    private InMemoryUserRepository $users;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->users->save(Users::active(self::USER_ID, 'maria'));
        $this->users->save(Users::active(self::ADMIN_ID, 'admin', Role::ADMIN));
        $this->users->save(Users::pending(self::PENDING_ID, 'pedro', SequentialActivationTokenGenerator::nth(1)));
    }

    #[Test]
    public function an_admin_sees_every_user_ordered_by_username_with_their_status(): void
    {
        $summaries = ($this->handler())(new ListUsersQuery(self::ADMIN_ID));

        self::assertEquals([
            new UserSummary(self::ADMIN_ID, 'admin', 'ADMIN', 'ACTIVE', Users::at(Users::CREATED_AT), null),
            new UserSummary(self::USER_ID, 'maria', 'USER', 'ACTIVE', Users::at(Users::CREATED_AT), null),
            new UserSummary(self::PENDING_ID, 'pedro', 'USER', 'PENDING_ACTIVATION', Users::at(Users::CREATED_AT), Users::at('2026-11-12 10:00:00')),
        ], $summaries);
    }

    #[Test]
    public function a_user_who_is_not_an_admin_cannot_list_users(): void
    {
        $this->expectException(AdminRequired::class);

        ($this->handler())(new ListUsersQuery(self::USER_ID));
    }

    private function handler(): ListUsersHandler
    {
        return new ListUsersHandler($this->users, new AdminActor($this->users));
    }
}
