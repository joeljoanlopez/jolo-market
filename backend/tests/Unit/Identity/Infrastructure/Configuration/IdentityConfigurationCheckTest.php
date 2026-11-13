<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Configuration;

use App\Identity\Infrastructure\Configuration\IdentityConfigurationCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentityConfigurationCheckTest extends TestCase
{
    #[Test]
    public function it_accepts_32_random_bytes_in_base64(): void
    {
        self::assertSame([], new IdentityConfigurationCheck(base64_encode(random_bytes(32)))->problems());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function missingKeys(): iterable
    {
        yield 'unset' => [null];
        yield 'empty' => [''];
    }

    #[Test]
    #[DataProvider('missingKeys')]
    public function it_reports_a_missing_key(?string $key): void
    {
        self::assertSame(
            ['TOTP_ENCRYPTION_KEY is missing: generate one with php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"'],
            new IdentityConfigurationCheck($key)->problems(),
        );
    }

    #[Test]
    public function it_reports_a_key_of_the_wrong_length(): void
    {
        self::assertSame(
            ['TOTP_ENCRYPTION_KEY is invalid: TOTP_ENCRYPTION_KEY must be 32 random bytes encoded in base64.'],
            new IdentityConfigurationCheck(base64_encode(random_bytes(16)))->problems(),
        );
    }
}
