<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Api;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Users are stored through the real repository with real hashes and encrypted secrets, so
 * requests go through the whole stack. Users and rate limits are cleared before each test.
 */
abstract class IdentityApiTestCase extends WebTestCase
{
    protected const string PASSWORD = 'Correct-horse-1';
    protected const string TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected KernelBrowser $client;

    private int $nextId = 1;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('DELETE FROM user');
        $rateLimits = self::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheInterface::class, $rateLimits);
        self::assertTrue(method_exists($rateLimits, 'clear') && $rateLimits->clear());
    }

    protected function users(): UserRepository
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);

        return $users;
    }

    protected function activeUser(string $username, Role $role = Role::USER): User
    {
        $encrypter = self::getContainer()->get(SecretEncrypter::class);
        self::assertInstanceOf(SecretEncrypter::class, $encrypter);
        $user = User::reconstitute(
            UserId::fromString(\sprintf('0192f3a4-7b1c-7d2e-8f30-%012d', $this->nextId++)),
            Username::fromString($username),
            $role,
            UserStatus::ACTIVE,
            PasswordHash::fromString((new NativePasswordHasher(cost: 4))->hash(self::PASSWORD)),
            $encrypter->encrypt(self::TOTP_SECRET),
            new \DateTimeImmutable('2026-11-09 10:00:00'),
            null,
            null,
            new \DateTimeImmutable('2026-11-09 10:00:00'),
            null,
            1,
            null,
        );
        $this->users()->save($user);

        return $user;
    }

    /**
     * Stores a user pending activation whose link is $plainToken and expires in 72 hours.
     */
    protected function pendingUser(string $username, string $plainToken, Role $role = Role::USER): User
    {
        $user = User::createPending(
            UserId::fromString(\sprintf('0192f3a4-7b1c-7d2e-8f30-%012d', $this->nextId++)),
            Username::fromString($username),
            $role,
            ActivationToken::issue($plainToken, new \DateTimeImmutable()),
            new \DateTimeImmutable(),
            null,
        );
        $this->users()->save($user);

        return $user;
    }

    protected static function codeFor(string $secret = self::TOTP_SECRET): string
    {
        if ('' === $secret) {
            self::fail('A TOTP secret cannot be empty.');
        }

        return TOTP::createFromSecret($secret, new NativeClock())->now();
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function postJson(string $uri, array $body = [], bool $withCsrfHeader = true): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($withCsrfHeader) {
            $server['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        }

        $this->client->request('POST', $uri, server: $server, content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param bool $forgetUsedCode the current code can be used once only (no replay); forgetting
     *                             it lets the test use the same code again within its 30 seconds
     */
    protected function logIn(string $username, bool $forgetUsedCode = true): void
    {
        $this->postJson('/api/auth/login', ['username' => $username, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(202);
        $this->postJson('/api/auth/2fa', ['code' => self::codeFor()]);
        self::assertResponseIsSuccessful();

        if ($forgetUsedCode) {
            $this->forgetUsedCodes();
        }
    }

    /**
     * Lets a test use the current code again within its 30 seconds (codes are single-use).
     */
    protected function forgetUsedCodes(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('UPDATE user SET totp_last_used_step = NULL');
    }

    /**
     * @return array<mixed>
     */
    protected function json(): array
    {
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
