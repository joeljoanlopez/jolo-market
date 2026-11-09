<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Api;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class AdminUsersApiTest extends IdentityApiTestCase
{
    #[Test]
    public function an_admin_creates_a_user_and_gets_the_activation_token_once(): void
    {
        $this->activeUser('admin', Role::ADMIN);
        $this->logIn('admin');

        $this->postJson('/api/admin/users', ['username' => 'Pedro', 'role' => 'USER']);

        self::assertResponseStatusCodeSame(201);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $created = $this->json();
        self::assertSame('pedro', $created['username']);
        self::assertIsString($created['id']);
        self::assertIsString($created['activationToken']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $created['activationToken']);
        self::assertIsString($created['activationExpiresAt']);

        $this->client->request('GET', '/api/auth/activation/'.$created['activationToken']);
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function an_admin_lists_every_user_with_their_status(): void
    {
        $admin = $this->activeUser('admin', Role::ADMIN);
        $this->pendingUser('pedro', 'activation-token-0000000000000000000042');
        $this->logIn('admin');

        $this->client->request('GET', '/api/admin/users');

        self::assertResponseStatusCodeSame(200);
        $users = $this->json();
        self::assertCount(2, $users);
        self::assertIsArray($users[0]);
        self::assertSame(
            ['id' => $admin->id()->value(), 'username' => 'admin', 'role' => 'ADMIN', 'status' => 'ACTIVE', 'createdAt' => '2026-11-09T10:00:00+00:00', 'activationExpiresAt' => null],
            $users[0],
        );
        self::assertIsArray($users[1]);
        self::assertSame(['pedro', 'PENDING_ACTIVATION'], [$users[1]['username'], $users[1]['status']]);
        self::assertIsString($users[1]['activationExpiresAt']);
    }

    #[Test]
    public function a_taken_username_is_a_409_and_an_invalid_one_a_422(): void
    {
        $this->activeUser('admin', Role::ADMIN);
        $this->logIn('admin');

        $this->postJson('/api/admin/users', ['username' => 'ADMIN', 'role' => 'USER']);
        self::assertResponseStatusCodeSame(409);

        $this->postJson('/api/admin/users', ['username' => 'x', 'role' => 'USER']);
        self::assertResponseStatusCodeSame(422);

        $this->postJson('/api/admin/users', ['username' => 'pedro', 'role' => 'OWNER']);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function an_admin_regenerates_the_link_of_a_pending_user(): void
    {
        $this->activeUser('admin', Role::ADMIN);
        $pedro = $this->pendingUser('pedro', 'activation-token-0000000000000000000042');
        $this->logIn('admin');

        $this->postJson('/api/admin/users/'.$pedro->id()->value().'/activation-link');

        self::assertResponseStatusCodeSame(200);
        $token = $this->json()['activationToken'];
        self::assertIsString($token);
        $this->client->request('GET', '/api/auth/activation/activation-token-0000000000000000000042');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/auth/activation/'.$token);
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function an_admin_resets_a_user_whose_sessions_then_end(): void
    {
        $this->activeUser('admin', Role::ADMIN);
        $maria = $this->activeUser('maria');
        $this->logIn('maria');
        $mariaCookies = $this->client->getCookieJar()->all();
        $this->client->getCookieJar()->clear();
        $this->logIn('admin');

        $this->postJson('/api/admin/users/'.$maria->id()->value().'/reset');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('maria', $this->json()['username']);
        self::assertSame(UserStatus::PENDING_ACTIVATION, $this->users()->findByUsername(Username::fromString('maria'))?->status());

        $this->client->getCookieJar()->clear();
        foreach ($mariaCookies as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function an_admin_disables_enables_and_changes_the_role_of_a_user(): void
    {
        $this->activeUser('admin', Role::ADMIN);
        $maria = $this->activeUser('maria');
        $this->logIn('admin');
        $base = '/api/admin/users/'.$maria->id()->value();

        $this->postJson($base.'/disable');
        self::assertResponseStatusCodeSame(204);
        self::assertSame(UserStatus::DISABLED, $this->users()->findById($maria->id())?->status());

        $this->postJson($base.'/enable');
        self::assertResponseStatusCodeSame(204);
        self::assertSame(UserStatus::ACTIVE, $this->users()->findById($maria->id())?->status());

        $this->client->request('PUT', $base.'/role', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], content: '{"role":"ADMIN"}');
        self::assertResponseStatusCodeSame(204);
        self::assertSame(Role::ADMIN, $this->users()->findById($maria->id())?->role());
    }

    #[Test]
    public function the_last_active_admin_cannot_demote_themselves(): void
    {
        $admin = $this->activeUser('admin', Role::ADMIN);
        $this->logIn('admin');

        $this->client->request('PUT', '/api/admin/users/'.$admin->id()->value().'/role', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], content: '{"role":"USER"}');

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function an_unknown_user_is_a_404(): void
    {
        $this->activeUser('admin', Role::ADMIN);
        $this->logIn('admin');

        $this->postJson('/api/admin/users/0192f3a4-7b1c-7d2e-8f30-000000000099/disable');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function adminRequests(): iterable
    {
        yield 'list' => ['GET', '/api/admin/users'];
        yield 'create' => ['POST', '/api/admin/users'];
        yield 'regenerate link' => ['POST', '/api/admin/users/{id}/activation-link'];
        yield 'reset' => ['POST', '/api/admin/users/{id}/reset'];
        yield 'disable' => ['POST', '/api/admin/users/{id}/disable'];
        yield 'enable' => ['POST', '/api/admin/users/{id}/enable'];
        yield 'change role' => ['PUT', '/api/admin/users/{id}/role'];
    }

    #[Test]
    #[DataProvider('adminRequests')]
    public function a_user_who_is_not_an_admin_gets_a_404(string $method, string $uri): void
    {
        $maria = $this->activeUser('maria');
        $this->logIn('maria');

        $this->client->request($method, str_replace('{id}', $maria->id()->value(), $uri), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], content: '{"username":"pedro","role":"ADMIN"}');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(Role::USER, $this->users()->findById($maria->id())?->role());
    }

    #[Test]
    #[DataProvider('adminRequests')]
    public function a_request_without_a_session_gets_a_401(string $method, string $uri): void
    {
        $this->client->request($method, str_replace('{id}', '0192f3a4-7b1c-7d2e-8f30-000000000001', $uri), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], content: '{}');

        self::assertResponseStatusCodeSame(401);
    }
}
