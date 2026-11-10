<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Api;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\Username;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SetupApiTest extends IdentityApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('DELETE FROM setup_code');
    }

    #[Test]
    public function a_fresh_instance_requires_setup(): void
    {
        $this->client->request('GET', '/api/setup');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['required' => true], $this->json());
    }

    #[Test]
    public function the_code_from_the_logs_creates_the_first_admin_who_then_activates(): void
    {
        $code = $this->ensureCode();

        $this->postJson('/api/setup', ['code' => $code, 'username' => 'Admin']);

        self::assertResponseStatusCodeSame(201);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $created = $this->json();
        self::assertSame('admin', $created['username']);
        self::assertIsString($created['activationToken']);
        $admin = $this->users()->findByUsername(Username::fromString('admin')) ?? self::fail('No admin.');
        self::assertSame(Role::ADMIN, $admin->role());
        self::assertSame(UserStatus::PENDING_ACTIVATION, $admin->status());

        $this->postJson('/api/auth/activation', ['token' => $created['activationToken']]);
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function a_wrong_code_is_a_422(): void
    {
        $this->ensureCode();

        $this->postJson('/api/setup', ['code' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ', 'username' => 'admin']);

        self::assertResponseStatusCodeSame(422);
        self::assertFalse($this->users()->hasAnyUser());
    }

    #[Test]
    public function the_code_cannot_be_used_twice_and_setup_is_then_gone(): void
    {
        $code = $this->ensureCode();
        $this->postJson('/api/setup', ['code' => $code, 'username' => 'admin']);

        $this->postJson('/api/setup', ['code' => $code, 'username' => 'another']);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/setup');
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function setup_answers_404_once_any_user_exists(): void
    {
        $this->activeUser('maria');

        $this->client->request('GET', '/api/setup');
        self::assertResponseStatusCodeSame(404);

        $this->postJson('/api/setup', ['code' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ', 'username' => 'admin']);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function setup_attempts_are_limited_per_ip_address(): void
    {
        $code = $this->ensureCode();
        for ($attempt = 1; $attempt <= 10; ++$attempt) {
            $this->postJson('/api/setup', ['code' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ', 'username' => 'admin']);
            self::assertResponseStatusCodeSame(422);
        }

        $this->postJson('/api/setup', ['code' => $code, 'username' => 'admin']);

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function the_startup_command_prints_a_new_code_only_while_there_are_no_users(): void
    {
        $first = $this->ensureCode();
        $second = $this->ensureCode();
        self::assertNotSame($first, $second);

        $this->postJson('/api/setup', ['code' => $first, 'username' => 'admin']);
        self::assertResponseStatusCodeSame(422);

        $this->activeUser('maria');
        $command = $this->ensureCodeCommand();
        $command->execute([]);
        self::assertSame(0, $command->getStatusCode());
        self::assertStringNotContainsString('Setup code', $command->getDisplay());
    }

    private function ensureCode(): string
    {
        $command = $this->ensureCodeCommand();
        $command->execute([]);
        self::assertSame(0, $command->getStatusCode());
        self::assertSame(1, preg_match('/Setup code: ([0-9A-Z]{4}(?:-[0-9A-Z]{4}){3})/', $command->getDisplay(), $matches));

        return $matches[1];
    }

    private function ensureCodeCommand(): CommandTester
    {
        $kernel = $this->client->getKernel();

        return new CommandTester(new Application($kernel)->find('app:setup:ensure-code'));
    }
}
