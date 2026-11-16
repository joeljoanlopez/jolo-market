<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Dev;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Identity\Infrastructure\Security\OtphpTotpService;
use App\Tests\Support\Dev\DevEnvironment;
use App\Tests\Support\FixedClock;
use App\Tests\Support\Identity\Users;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PD-56: 2FA stays mandatory in dev, and this command tells the current code of any active user.
 */
final class PrintTotpCodeConsoleCommandTest extends KernelTestCase
{
    use DevEnvironment;

    private const string SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private UserRepository $users;
    private CommandTester $command;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->setUpDevEnvironment();
        $kernel = self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('DELETE FROM user');
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $this->users = $users;
        $clock = self::getContainer()->get('test.dev.clock');
        self::assertInstanceOf(FixedClock::class, $clock);
        $this->clock = $clock;

        $this->command = new CommandTester(new Application($kernel)->find('app:dev:totp'));
    }

    #[Test]
    public function it_prints_the_code_the_login_accepts_now_and_how_long_it_is_valid(): void
    {
        $this->storeActiveUser('maria');
        $now = $this->clock->now();

        $exitCode = $this->command->execute(['username' => 'maria']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, preg_match('/\b(\d{6})\b.*?(\d{1,2}) s/s', $this->command->getDisplay(), $matches), $this->command->getDisplay());
        self::assertSame(intdiv($now->getTimestamp(), 30), (new OtphpTotpService())->matchingTimeStep(self::SECRET, TotpCode::fromString($matches[1]), $now));
        self::assertSame(30 - $now->getTimestamp() % 30, (int) $matches[2]);
    }

    #[Test]
    public function it_fails_for_an_unknown_user(): void
    {
        $exitCode = $this->command->execute(['username' => 'nobody']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('"nobody" is not an active user', $this->command->getDisplay());
    }

    #[Test]
    public function it_fails_for_a_user_that_is_not_active(): void
    {
        $this->users->save(Users::pending('0192f3a4-7b1c-7d2e-8f30-000000000003', 'pending', str_repeat('t', 43)));

        $exitCode = $this->command->execute(['username' => 'pending']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('"pending" is not an active user', $this->command->getDisplay());
    }

    private function storeActiveUser(string $username): void
    {
        $encrypter = self::getContainer()->get(SecretEncrypter::class);
        self::assertInstanceOf(SecretEncrypter::class, $encrypter);
        $user = Users::active('0192f3a4-7b1c-7d2e-8f30-000000000001', $username, Role::USER);
        $user->startTotpReEnrollment($encrypter->encrypt(self::SECRET));
        $user->confirmTotpReEnrollment($this->clock->now());
        $this->users->save($user);
    }
}
