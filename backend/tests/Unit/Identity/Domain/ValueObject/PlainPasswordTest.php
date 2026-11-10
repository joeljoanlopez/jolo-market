<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\ValueObject\PlainPassword;
use App\Identity\Domain\ValueObject\WeakPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlainPasswordTest extends TestCase
{
    #[Test]
    #[DataProvider('strongPasswords')]
    public function it_accepts_a_password_that_follows_the_policy(string $value): void
    {
        self::assertSame($value, PlainPassword::fromString($value)->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function strongPasswords(): iterable
    {
        yield 'twelve characters' => ['Abcdefghi1!x'];
        yield 'a hundred and twenty-eight characters' => ['Aa1!'.str_repeat('x', 124)];
        yield 'a space is a special character' => ['Abcdefghi1 x'];
        yield 'accented letters count as letters' => ['Ñandú1!abcde'];
    }

    #[Test]
    #[DataProvider('weakPasswords')]
    public function it_rejects_a_password_that_breaks_the_policy(string $value): void
    {
        $this->expectException(WeakPassword::class);

        PlainPassword::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function weakPasswords(): iterable
    {
        yield 'eleven characters' => ['Abcdefgh1!x'];
        yield 'a hundred and twenty-nine characters' => ['Aa1!'.str_repeat('x', 125)];
        yield 'no uppercase letter' => ['abcdefghi1!x'];
        yield 'no lowercase letter' => ['ABCDEFGHI1!X'];
        yield 'no digit' => ['Abcdefghij!x'];
        yield 'no special character' => ['Abcdefghi1xy'];
        yield 'not valid UTF-8' => ["Abcdefghi1!\xff"];
    }

    #[Test]
    public function it_counts_characters_not_bytes(): void
    {
        // Eleven characters, but more than twelve bytes.
        $this->expectException(WeakPassword::class);

        PlainPassword::fromString('Ññññññññ1!a');
    }

    #[Test]
    public function its_error_never_reveals_the_password(): void
    {
        try {
            PlainPassword::fromString('secretpass1x');
            self::fail('The password should be rejected.');
        } catch (WeakPassword $error) {
            self::assertStringNotContainsString('secretpass1x', $error->getMessage());
        }
    }
}
