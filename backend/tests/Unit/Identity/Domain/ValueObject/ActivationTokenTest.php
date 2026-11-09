<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\InvalidActivationToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivationTokenTest extends TestCase
{
    private const string PLAIN = 'Zx9vQm3kT8wLr2Nf6Yb1Hc4Pj7Sd0Ga5Ue8Wq2Ki3Mo';

    #[Test]
    public function it_stores_only_the_hash_of_the_plain_token(): void
    {
        $token = ActivationToken::issue(self::PLAIN, self::at('2026-11-09 10:00:00'));

        self::assertSame(hash('sha256', self::PLAIN), $token->hash());
        self::assertSame(ActivationToken::hashOf(self::PLAIN), $token->hash());
        self::assertStringNotContainsString(self::PLAIN, $token->hash());
    }

    #[Test]
    public function it_expires_seventy_two_hours_after_it_is_issued(): void
    {
        $token = ActivationToken::issue(self::PLAIN, self::at('2026-11-09 10:00:00'));

        self::assertEquals(self::at('2026-11-12 10:00:00'), $token->expiresAt());
        self::assertFalse($token->isExpiredAt(self::at('2026-11-12 09:59:59')));
        self::assertTrue($token->isExpiredAt(self::at('2026-11-12 10:00:00')));
    }

    #[Test]
    public function it_matches_only_its_own_plain_token(): void
    {
        $token = ActivationToken::issue(self::PLAIN, self::at('2026-11-09 10:00:00'));

        self::assertTrue($token->matches(self::PLAIN));
        self::assertFalse($token->matches(self::PLAIN.'x'));
    }

    #[Test]
    public function it_refuses_a_plain_token_too_short_to_be_unguessable(): void
    {
        $this->expectException(InvalidActivationToken::class);

        ActivationToken::issue(str_repeat('a', 31), self::at('2026-11-09 10:00:00'));
    }

    #[Test]
    public function it_is_rebuilt_from_its_stored_hash_and_expiry(): void
    {
        $token = ActivationToken::reconstitute(hash('sha256', self::PLAIN), self::at('2026-11-12 10:00:00'));

        self::assertTrue($token->matches(self::PLAIN));
        self::assertEquals(self::at('2026-11-12 10:00:00'), $token->expiresAt());
    }

    private static function at(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
    }
}
