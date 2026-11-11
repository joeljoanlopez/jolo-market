<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\BrowserKit\Cookie;

/**
 * PD-37 and PD-40: the cookie is re-sent at most once an hour so the browser keeps it for 7 more
 * days. The first request after an activation renews it (the activation logs the user in without
 * a previous renewal), which is the easiest renewal to reach in a test.
 */
final class SessionRenewalApiTest extends IdentityApiTestCase
{
    private const string TOKEN = 'activation-token-for-the-session-renewal-tests';

    #[Test]
    public function a_renewal_sends_the_same_session_id_again_for_seven_more_days(): void
    {
        $sessionId = $this->activateAndGetSessionId();

        $this->client->request('GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(200);
        $renewed = $this->sessionCookieOfTheResponse();
        self::assertSame($sessionId, $renewed->getValue());
        self::assertEqualsWithDelta(time() + 604800, $renewed->getExpiresTime(), 60);
    }

    #[Test]
    public function a_request_sent_at_the_same_time_as_a_renewal_keeps_the_session(): void
    {
        $sessionId = $this->activateAndGetSessionId();
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(200);

        // A second request the browser sent before the renewal answer came back.
        $name = $this->sessionCookieName();
        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set(new Cookie($name, $sessionId));
        $this->client->request('GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(200);
    }

    private function activateAndGetSessionId(): string
    {
        $this->pendingUser('pedro', self::TOKEN);
        $this->postJson('/api/auth/activation/password', ['token' => self::TOKEN, 'password' => 'New-password-1!']);
        $secret = $this->json()['secret'];
        self::assertIsString($secret);
        $this->postJson('/api/auth/activation/totp', ['token' => self::TOKEN, 'code' => self::codeFor($secret)]);
        self::assertResponseStatusCodeSame(200);

        $cookie = $this->client->getCookieJar()->get($this->sessionCookieName());
        self::assertNotNull($cookie);

        return $cookie->getValue();
    }

    private function sessionCookieOfTheResponse(): \Symfony\Component\HttpFoundation\Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $this->sessionCookieName()) {
                return $cookie;
            }
        }

        self::fail('The response does not send the session cookie.');
    }

    private function sessionCookieName(): string
    {
        return null !== $this->client->getCookieJar()->get('MOCKSESSID') ? 'MOCKSESSID' : 'PHPSESSID';
    }
}
