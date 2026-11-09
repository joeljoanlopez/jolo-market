<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\UserDirectory;

use App\Identity\Application\UserDirectory\FindUsernamesHandler;
use App\Identity\Application\UserDirectory\FindUsernamesQuery;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FindUsernamesHandlerTest extends TestCase
{
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000002';

    #[Test]
    public function it_gives_the_username_of_each_known_id(): void
    {
        $users = new InMemoryUserRepository();
        $users->save(Users::active(self::MARIA, 'maria'));
        $users->save(Users::active(self::PEDRO, 'pedro'));

        $usernames = (new FindUsernamesHandler($users))(new FindUsernamesQuery([self::PEDRO, self::MARIA]));

        self::assertSame([self::PEDRO => 'pedro', self::MARIA => 'maria'], $usernames);
    }

    #[Test]
    public function it_leaves_out_unknown_and_malformed_ids(): void
    {
        $users = new InMemoryUserRepository();
        $users->save(Users::active(self::MARIA, 'maria'));

        $usernames = (new FindUsernamesHandler($users))(new FindUsernamesQuery([self::MARIA, self::PEDRO, 'not-an-id']));

        self::assertSame([self::MARIA => 'maria'], $usernames);
    }

    #[Test]
    public function it_returns_nothing_for_no_ids(): void
    {
        self::assertSame([], (new FindUsernamesHandler(new InMemoryUserRepository()))(new FindUsernamesQuery([])));
    }
}
