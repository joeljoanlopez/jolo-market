<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\ValueObject\InvalidUsername;
use App\Identity\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UsernameTest extends TestCase
{
    #[Test]
    #[DataProvider('validUsernames')]
    public function it_accepts_a_valid_username(string $value): void
    {
        self::assertSame($value, Username::fromString($value)->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validUsernames(): iterable
    {
        yield 'three characters' => ['ana'];
        yield 'thirty-two characters' => [str_repeat('a', 32)];
        yield 'dot, underscore and hyphen' => ['maria.lopez_2-b'];
        yield 'starts with a digit' => ['7maria'];
    }

    #[Test]
    public function it_is_normalized_to_lowercase(): void
    {
        self::assertSame('maria', Username::fromString('Maria')->value());
    }

    #[Test]
    public function it_ignores_case_when_compared(): void
    {
        self::assertTrue(Username::fromString('MARIA')->equals(Username::fromString('maria')));
        self::assertFalse(Username::fromString('maria')->equals(Username::fromString('mario')));
    }

    #[Test]
    #[DataProvider('invalidUsernames')]
    public function it_rejects_an_invalid_username(string $value): void
    {
        $this->expectException(InvalidUsername::class);

        Username::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUsernames(): iterable
    {
        yield 'too short' => ['ab'];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'starts with a dot' => ['.maria'];
        yield 'starts with an underscore' => ['_maria'];
        yield 'starts with a hyphen' => ['-maria'];
        yield 'accent' => ['maría'];
        yield 'eñe' => ['nuño'];
        yield 'space' => ['maria lopez'];
        yield 'at sign' => ['maria@home'];
        yield 'empty' => [''];
        yield 'trailing newline' => ["maria\n"];
    }
}
