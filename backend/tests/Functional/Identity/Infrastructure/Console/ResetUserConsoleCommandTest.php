<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Console;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\Username;
use App\Tests\Support\Identity\Users;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ResetUserConsoleCommandTest extends KernelTestCase
{
    private UserRepository $users;
    private CommandTester $command;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('DELETE FROM user');
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $this->users = $users;

        $this->command = new CommandTester(new Application($kernel)->find('app:user:reset'));
    }

    #[Test]
    public function it_resets_a_locked_out_admin_and_prints_their_new_activation_link(): void
    {
        $this->users->save(Users::active('0192f3a4-7b1c-7d2e-8f30-000000000001', 'admin', Role::ADMIN));

        $exitCode = $this->command->execute(['username' => 'Admin']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $admin = $this->users->findByUsername(Username::fromString('admin')) ?? self::fail('The admin is gone.');
        self::assertSame(UserStatus::PENDING_ACTIVATION, $admin->status());
        $output = $this->command->getDisplay();
        self::assertMatchesRegularExpression('~http://localhost/activate/([A-Za-z0-9_-]{43})~', $output);
        self::assertSame(1, preg_match('~/activate/([A-Za-z0-9_-]{43})~', $output, $matches));
        self::assertTrue($admin->activation()?->matches($matches[1]));
        self::assertStringContainsString('admin', $output);
    }

    #[Test]
    public function it_fails_for_an_unknown_username(): void
    {
        $exitCode = $this->command->execute(['username' => 'nobody']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('User "nobody" was not found.', $this->command->getDisplay());
    }
}
