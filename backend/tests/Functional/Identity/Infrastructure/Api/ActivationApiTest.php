<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Api;

use App\Identity\Domain\Model\UserStatus;
use App\Identity\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\Test;

final class ActivationApiTest extends IdentityApiTestCase
{
    private const string TOKEN = 'activation-token-0000000000000000000042';

    #[Test]
    public function a_user_activates_their_account_and_is_logged_in(): void
    {
        $pedro = $this->pendingUser('pedro', self::TOKEN);

        $this->client->request('GET', '/api/auth/activation/'.self::TOKEN);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('pedro', $this->json()['username']);
        self::assertIsString($this->json()['expiresAt']);

        $this->postJson('/api/auth/activation/'.self::TOKEN.'/password', ['password' => 'New-password-1!']);
        self::assertResponseStatusCodeSame(200);
        $enrollment = $this->json();
        self::assertIsString($enrollment['secret']);
        self::assertIsString($enrollment['provisioningUri']);
        self::assertStringStartsWith('otpauth://totp/jolo-market%3Apedro?', $enrollment['provisioningUri']);

        $this->postJson('/api/auth/activation/'.self::TOKEN.'/totp', ['code' => self::codeFor($enrollment['secret'])]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['id' => $pedro->id()->value(), 'username' => 'pedro', 'role' => 'USER'], $this->json());

        self::assertSame(UserStatus::ACTIVE, $this->users()->findByUsername(Username::fromString('pedro'))?->status());
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function an_unknown_link_is_a_404(): void
    {
        $this->client->request('GET', '/api/auth/activation/activation-token-0000000000000000000099');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function a_used_link_is_a_404(): void
    {
        $this->pendingUser('pedro', self::TOKEN);
        $this->postJson('/api/auth/activation/'.self::TOKEN.'/password', ['password' => 'New-password-1!']);
        $secret = $this->json()['secret'];
        self::assertIsString($secret);
        $this->postJson('/api/auth/activation/'.self::TOKEN.'/totp', ['code' => self::codeFor($secret)]);

        $this->client->request('GET', '/api/auth/activation/'.self::TOKEN);
        self::assertResponseStatusCodeSame(404);
        $this->postJson('/api/auth/activation/'.self::TOKEN.'/password', ['password' => 'New-password-1!']);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function a_weak_password_is_a_422_with_the_policy(): void
    {
        $this->pendingUser('pedro', self::TOKEN);

        $this->postJson('/api/auth/activation/'.self::TOKEN.'/password', ['password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('The password must have between 12 and 128 characters.', $this->json()['detail']);
    }

    #[Test]
    public function a_wrong_code_is_a_422_and_the_user_stays_pending(): void
    {
        $this->pendingUser('pedro', self::TOKEN);
        $this->postJson('/api/auth/activation/'.self::TOKEN.'/password', ['password' => 'New-password-1!']);
        $secret = $this->json()['secret'];
        self::assertIsString($secret);

        $this->postJson('/api/auth/activation/'.self::TOKEN.'/totp', ['code' => '000000' === self::codeFor($secret) ? '111111' : '000000']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(UserStatus::PENDING_ACTIVATION, $this->users()->findByUsername(Username::fromString('pedro'))?->status());
    }

    #[Test]
    public function activation_code_attempts_are_limited_per_ip_address(): void
    {
        $this->pendingUser('pedro', self::TOKEN);
        $this->postJson('/api/auth/activation/'.self::TOKEN.'/password', ['password' => 'New-password-1!']);
        $secret = $this->json()['secret'];
        self::assertIsString($secret);
        $wrong = '000000' === self::codeFor($secret) ? '111111' : '000000';
        for ($attempt = 1; $attempt <= 20; ++$attempt) {
            $this->postJson('/api/auth/activation/'.self::TOKEN.'/totp', ['code' => $wrong]);
            self::assertResponseStatusCodeSame(422);
        }

        $this->postJson('/api/auth/activation/'.self::TOKEN.'/totp', ['code' => self::codeFor($secret)]);

        self::assertResponseStatusCodeSame(429);
    }
}
