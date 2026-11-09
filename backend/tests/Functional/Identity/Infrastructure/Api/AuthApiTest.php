<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Api;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\ValueObject\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

final class AuthApiTest extends IdentityApiTestCase
{
    #[Test]
    public function a_user_logs_in_with_password_then_code_and_sees_who_they_are(): void
    {
        $maria = $this->activeUser('maria');

        $this->postJson('/api/auth/login', ['username' => 'Maria', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(202);
        self::assertSame(['twoFactorRequired' => true], $this->json());

        $this->postJson('/api/auth/2fa', ['code' => self::codeFor()]);
        self::assertResponseStatusCodeSame(200);
        $me = ['id' => $maria->id()->value(), 'username' => 'maria', 'role' => 'USER'];
        self::assertSame($me, $this->json());

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(200);
        self::assertSame($me, $this->json());
    }

    #[Test]
    public function the_session_cookie_is_http_only_and_same_site_lax(): void
    {
        $this->activeUser('maria');

        $this->logIn('maria');

        $cookie = $this->client->getCookieJar()->get('MOCKSESSID') ?? $this->client->getCookieJar()->get('PHPSESSID');
        self::assertNotNull($cookie);
        $config = self::getContainer()->getParameter('session.storage.options');
        self::assertIsArray($config);
        self::assertTrue($config['cookie_httponly']);
        self::assertSame('lax', $config['cookie_samesite']);
        self::assertSame('auto', $config['cookie_secure']);
        self::assertSame(604800, $config['gc_maxlifetime']);
    }

    #[Test]
    public function a_password_alone_does_not_open_the_session(): void
    {
        $this->activeUser('maria');

        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => self::PASSWORD]);
        $this->client->request('GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function a_wrong_password_and_an_unknown_user_get_the_same_401(): void
    {
        $this->activeUser('maria');

        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => 'Wrong-password-1']);
        self::assertResponseStatusCodeSame(401);
        $wrongPassword = $this->json();

        $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(401);

        self::assertSame($wrongPassword, $this->json());
        self::assertSame(['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => 401, 'detail' => 'Invalid credentials.'], $wrongPassword);
    }

    #[Test]
    public function a_wrong_code_gets_the_same_401_and_keeps_the_session_closed(): void
    {
        $this->activeUser('maria');
        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => self::PASSWORD]);

        $this->postJson('/api/auth/2fa', ['code' => '000000' === self::codeFor() ? '111111' : '000000']);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('Invalid credentials.', $this->json()['detail']);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function a_code_without_a_password_first_is_refused(): void
    {
        $this->activeUser('maria');

        $this->postJson('/api/auth/2fa', ['code' => self::codeFor()]);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function login_attempts_are_limited_per_username(): void
    {
        $this->activeUser('maria');
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => 'Wrong-password-1']);
            self::assertResponseStatusCodeSame(401);
        }

        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertTrue($this->client->getResponse()->headers->has('Retry-After'));
    }

    #[Test]
    public function login_attempts_are_limited_per_ip_address_across_usernames(): void
    {
        for ($attempt = 1; $attempt <= 20; ++$attempt) {
            $this->postJson('/api/auth/login', ['username' => 'user'.$attempt, 'password' => 'Wrong-password-1']);
            self::assertResponseStatusCodeSame(401);
        }

        $this->postJson('/api/auth/login', ['username' => 'another', 'password' => 'Wrong-password-1']);

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function code_attempts_are_limited_per_user(): void
    {
        $this->activeUser('maria');
        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => self::PASSWORD]);
        $wrong = '000000' === self::codeFor() ? '111111' : '000000';
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->postJson('/api/auth/2fa', ['code' => $wrong]);
            self::assertResponseStatusCodeSame(401);
        }

        $this->postJson('/api/auth/2fa', ['code' => self::codeFor()]);

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function a_successful_login_clears_the_failed_attempts_of_the_username(): void
    {
        $this->activeUser('maria');
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => 'Wrong-password-1']);
        }
        $this->logIn('maria');
        $this->postJson('/api/auth/logout');

        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => 'Wrong-password-1']);
            self::assertResponseStatusCodeSame(401);
        }
    }

    #[Test]
    public function logging_out_ends_the_session(): void
    {
        $this->activeUser('maria');
        $this->logIn('maria');

        $this->postJson('/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function a_session_ends_when_the_user_is_disabled_elsewhere(): void
    {
        $maria = $this->activeUser('maria');
        $this->logIn('maria');

        $maria->disable();
        $this->users()->save($maria);
        $this->client->request('GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function a_session_ends_when_the_password_changes_elsewhere(): void
    {
        $maria = $this->activeUser('maria');
        $this->logIn('maria');

        $maria->changePassword(PasswordHash::fromString((new NativePasswordHasher(cost: 4))->hash('Other-password-2')));
        $this->users()->save($maria);
        $this->client->request('GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function a_role_change_ends_the_session_so_its_roles_are_never_stale(): void
    {
        $maria = $this->activeUser('maria', Role::ADMIN);
        $this->logIn('maria');

        $maria->changeRole(Role::USER);
        $this->users()->save($maria);
        $this->client->request('GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function a_login_code_cannot_be_used_twice(): void
    {
        $this->activeUser('maria');
        $this->logIn('maria', forgetUsedCode: false);
        $this->postJson('/api/auth/logout');

        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => self::PASSWORD]);
        $this->postJson('/api/auth/2fa', ['code' => self::codeFor()]);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function a_state_changing_request_without_the_csrf_header_is_rejected(): void
    {
        $this->activeUser('maria');

        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => self::PASSWORD], withCsrfHeader: false);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function a_cross_site_form_post_cannot_log_out_a_user(): void
    {
        $this->activeUser('maria');
        $this->logIn('maria');

        // A form on another site can only send a simple request: no custom headers.
        $this->client->request('POST', '/api/auth/logout', ['_' => '1'], server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function a_malformed_body_is_a_422(): void
    {
        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], content: '{"username": 3}');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function a_logged_in_user_gets_a_404_for_an_unknown_route(): void
    {
        $this->activeUser('maria');
        $this->logIn('maria');

        $this->client->request('GET', '/api/this-route-does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function the_health_check_and_the_catalog_stay_public_for_now(): void
    {
        $this->client->request('GET', '/api/health');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/supermarkets');
        self::assertResponseIsSuccessful();
    }
}
