<?php

declare(strict_types=1);

namespace App\Tests\Integration\Receipts\Infrastructure\Identity;

use App\Identity\Application\UserDirectory\FindUsernamesHandler;
use App\Receipts\Application\ReceiptUserDirectory;
use App\Receipts\Infrastructure\Identity\IdentityReceiptUserDirectory;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Support\Identity\InMemoryUserRepository;
use App\Tests\Support\Identity\Users;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IdentityReceiptUserDirectoryTest extends KernelTestCase
{
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string UNKNOWN = '0192f3a4-7b1c-7d2e-8f30-000000000099';

    #[Test]
    public function it_reads_usernames_through_the_identity_query_api(): void
    {
        $users = new InMemoryUserRepository();
        $users->save(Users::active(self::MARIA, 'maria'));
        $directory = new IdentityReceiptUserDirectory(new FindUsernamesHandler($users));

        self::assertSame([self::MARIA => 'maria'], $directory->usernamesOf([UserId::fromString(self::MARIA), UserId::fromString(self::UNKNOWN)]));
        self::assertSame([], $directory->usernamesOf([]));
    }

    #[Test]
    public function it_is_the_directory_the_application_gets(): void
    {
        self::assertInstanceOf(IdentityReceiptUserDirectory::class, self::getContainer()->get(ReceiptUserDirectory::class));
    }
}
