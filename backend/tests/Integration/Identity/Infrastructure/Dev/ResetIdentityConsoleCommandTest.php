<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\Infrastructure\Dev;

use App\Identity\Infrastructure\Dev\DevUsers;
use App\Shared\Domain\Clock;
use App\Tests\Support\Dev\DevEnvironment;
use App\Tests\Support\Dev\DevTables;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PD-57: the users reset empties only the tables of the Identity context, and every session ends.
 */
final class ResetIdentityConsoleCommandTest extends WebTestCase
{
    use DevEnvironment;

    private KernelBrowser $client;
    private DevTables $tables;
    private Application $application;

    protected function setUp(): void
    {
        $this->setUpDevEnvironment();
        $this->client = self::createClient();
        $this->application = new Application($this->client->getKernel());
        $this->tables = DevTables::of(self::getContainer());
        $this->tables->emptyAll();
    }

    #[Test]
    public function it_empties_users_and_the_setup_code_only(): void
    {
        $this->tables->storeUserAndList();
        $this->tables->storeSetupCode();

        $exitCode = new CommandTester($this->application->find('app:dev:users:reset'))->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(0, $this->tables->rows('user'));
        self::assertSame(0, $this->tables->rows('setup_code'));
        self::assertSame(1, $this->tables->rows('shopping_list'));
    }

    #[Test]
    public function it_ends_every_session(): void
    {
        new CommandTester($this->application->find('app:dev:users'))->execute([]);
        $this->logIn(DevUsers::ADMIN);
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();

        new CommandTester($this->application->find('app:dev:users:reset'))->execute(['--force' => true]);
        new CommandTester($this->application->find('app:dev:users'))->execute([]);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function without_interaction_it_refuses_unless_forced(): void
    {
        $this->tables->storeUserAndList();

        $exitCode = new CommandTester($this->application->find('app:dev:users:reset'))->execute([], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(1, $this->tables->rows('user'));
    }

    private function logIn(string $username): void
    {
        $this->postJson('/api/auth/login', ['username' => $username, 'password' => DevUsers::PASSWORD]);
        self::assertResponseStatusCodeSame(202);
        $clock = self::getContainer()->get(Clock::class);
        self::assertInstanceOf(Clock::class, $clock);
        $timestamp = $clock->now()->getTimestamp();
        $secret = DevUsers::totpSecretOf($username);
        if ($timestamp < 0 || '' === $secret) {
            self::fail('Cannot compute a TOTP code.');
        }
        $this->postJson('/api/auth/2fa', ['code' => TOTP::createFromSecret($secret, new NativeClock())->at($timestamp)]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $uri, array $body): void
    {
        $this->client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }
}
