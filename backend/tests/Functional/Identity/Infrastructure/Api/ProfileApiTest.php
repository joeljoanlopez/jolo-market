<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Api;

use App\Identity\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\Test;

final class ProfileApiTest extends IdentityApiTestCase
{
    private const string NEW_PASSWORD = 'Brand-new-pass-2';

    #[Test]
    public function a_user_changes_their_password_stays_logged_in_and_their_other_sessions_end(): void
    {
        $this->activeUser('maria');
        $this->logIn('maria');
        $otherSession = $this->client->getCookieJar()->all();
        $this->client->getCookieJar()->clear();
        $this->logIn('maria');

        $this->postJson('/api/me/password', ['currentPassword' => self::PASSWORD, 'code' => self::codeFor(), 'newPassword' => self::NEW_PASSWORD]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(200);

        $currentSession = $this->client->getCookieJar()->all();
        $this->client->getCookieJar()->clear();
        foreach ($otherSession as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);

        $this->client->getCookieJar()->clear();
        foreach ($currentSession as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => self::NEW_PASSWORD]);
        self::assertResponseStatusCodeSame(202);
    }

    #[Test]
    public function a_wrong_current_password_is_a_422_and_nothing_changes(): void
    {
        $maria = $this->activeUser('maria');
        $this->logIn('maria');

        $this->postJson('/api/me/password', ['currentPassword' => 'Wrong-password-1', 'code' => self::codeFor(), 'newPassword' => self::NEW_PASSWORD]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($maria->passwordHash()?->value(), $this->users()->findById($maria->id())?->passwordHash()?->value());
    }

    #[Test]
    public function a_user_re_enrolls_totp_and_stays_logged_in(): void
    {
        $this->activeUser('maria');
        $this->logIn('maria');

        $this->postJson('/api/me/totp', ['currentPassword' => self::PASSWORD, 'code' => self::codeFor()]);
        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $secret = $this->json()['secret'];
        self::assertIsString($secret);
        self::assertIsString($this->json()['provisioningUri']);

        $this->postJson('/api/me/totp/confirm', ['code' => self::codeFor($secret)]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(200);
        $stored = $this->users()->findByUsername(Username::fromString('maria'))?->totpSecret();
        self::assertNotNull($stored);
    }

    #[Test]
    public function profile_credential_checks_are_rate_limited(): void
    {
        $this->activeUser('maria');
        $this->logIn('maria');
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->postJson('/api/me/totp', ['currentPassword' => 'Wrong-password-1', 'code' => self::codeFor()]);
            self::assertResponseStatusCodeSame(422);
        }

        $this->postJson('/api/me/totp', ['currentPassword' => self::PASSWORD, 'code' => self::codeFor()]);

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function the_profile_needs_a_session(): void
    {
        $this->postJson('/api/me/password', ['currentPassword' => self::PASSWORD, 'code' => '123456', 'newPassword' => self::NEW_PASSWORD]);

        self::assertResponseStatusCodeSame(401);
    }
}
