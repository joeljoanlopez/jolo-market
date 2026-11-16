<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Dev;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\Username;
use App\Identity\Infrastructure\Dev\DevUsers;
use App\Shared\Domain\Clock;
use App\Tests\Support\Dev\DevEnvironment;
use App\Tests\Support\Identity\Users;
use Doctrine\DBAL\Connection;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PD-56: the dummy users can log in at once, with their password and a code of their fixed secret.
 */
final class CreateDevUsersConsoleCommandTest extends WebTestCase
{
    use DevEnvironment;

    private KernelBrowser $client;
    private CommandTester $command;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->setUpDevEnvironment();
        $this->client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM user');

        $this->command = new CommandTester(new Application($this->client->getKernel())->find('app:dev:users'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dummyUsers(): iterable
    {
        yield 'admin' => [DevUsers::ADMIN, 'ADMIN'];
        yield 'usuario' => [DevUsers::USER, 'USER'];
    }

    #[Test]
    #[DataProvider('dummyUsers')]
    public function a_dummy_user_can_log_in_with_the_password_and_a_code_of_its_fixed_secret(string $username, string $role): void
    {
        $exitCode = $this->command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $this->postJson('/api/auth/login', ['username' => $username, 'password' => DevUsers::PASSWORD]);
        self::assertResponseStatusCodeSame(202);
        $this->postJson('/api/auth/2fa', ['code' => $this->currentCode(DevUsers::totpSecretOf($username))]);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(\sprintf('"role":"%s"', $role), (string) $this->client->getResponse()->getContent());
    }

    #[Test]
    public function it_leaves_an_existing_user_with_a_dummy_name_and_every_other_user_untouched(): void
    {
        $users = $this->users();
        $users->save(Users::active('0192f3a4-7b1c-7d2e-8f30-000000000001', 'admin', Role::USER));
        $users->save(Users::active('0192f3a4-7b1c-7d2e-8f30-000000000002', 'maria'));
        $before = $this->connection->fetchAllAssociative('SELECT * FROM user ORDER BY username');

        $this->command->execute([]);

        $after = $this->connection->fetchAllAssociative("SELECT * FROM user WHERE username <> 'usuario' ORDER BY username");
        self::assertSame($before, $after);
        self::assertNotNull($users->findByUsername(Username::fromString('usuario')));
        self::assertStringContainsString('"admin" already exists', $this->command->getDisplay());
    }

    #[Test]
    public function running_it_again_creates_nothing(): void
    {
        $this->command->execute([]);
        $before = $this->connection->fetchAllAssociative('SELECT * FROM user ORDER BY username');

        $exitCode = $this->command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM user ORDER BY username'));
    }

    private function currentCode(string $secret): string
    {
        $clock = self::getContainer()->get(Clock::class);
        self::assertInstanceOf(Clock::class, $clock);
        self::assertNotSame('', $secret);
        $timestamp = $clock->now()->getTimestamp();
        if ($timestamp < 0) {
            self::fail('The clock is before the Unix epoch.');
        }

        return TOTP::createFromSecret($secret, new NativeClock())->at($timestamp);
    }

    private function users(): UserRepository
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);

        return $users;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $uri, array $body): void
    {
        $this->client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }
}
