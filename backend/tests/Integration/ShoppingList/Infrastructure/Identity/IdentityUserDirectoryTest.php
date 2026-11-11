<?php

declare(strict_types=1);

namespace App\Tests\Integration\ShoppingList\Infrastructure\Identity;

use App\Identity\Application\UserDirectory\FindUsernamesHandler;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\UserDirectory;
use App\ShoppingList\Infrastructure\Identity\IdentityUserDirectory;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IdentityUserDirectoryTest extends KernelTestCase
{
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string JOAN = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string UNKNOWN = '0192f3a4-7b1c-7d2e-8f30-000000000099';

    #[Test]
    public function it_reads_usernames_through_the_identity_query_api(): void
    {
        $users = new InMemoryUserRepository();
        $users->save(Users::active(self::MARIA, 'maria'));
        $users->save(Users::active(self::JOAN, 'joan'));
        $directory = new IdentityUserDirectory(new FindUsernamesHandler($users));

        $usernames = $directory->usernamesOf([UserId::fromString(self::JOAN), UserId::fromString(self::UNKNOWN), UserId::fromString(self::MARIA)]);

        self::assertSame([self::JOAN => 'joan', self::MARIA => 'maria'], $usernames);
    }

    #[Test]
    public function no_ids_have_no_usernames(): void
    {
        self::assertSame([], (new IdentityUserDirectory(new FindUsernamesHandler(new InMemoryUserRepository())))->usernamesOf([]));
    }

    #[Test]
    public function it_is_the_directory_the_application_gets(): void
    {
        self::assertInstanceOf(IdentityUserDirectory::class, self::getContainer()->get(UserDirectory::class));
    }
}
