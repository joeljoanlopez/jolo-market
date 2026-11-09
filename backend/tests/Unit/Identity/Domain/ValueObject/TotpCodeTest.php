<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\ValueObject\InvalidTotpCode;
use App\Identity\Domain\ValueObject\TotpCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TotpCodeTest extends TestCase
{
    #[Test]
    public function it_keeps_six_digits(): void
    {
        self::assertSame('012345', TotpCode::fromString('012345')->value());
    }

    #[Test]
    public function it_ignores_surrounding_and_inner_spaces(): void
    {
        self::assertSame('123456', TotpCode::fromString(' 123 456 ')->value());
    }

    #[Test]
    #[DataProvider('invalidCodes')]
    public function it_rejects_anything_but_six_digits(string $value): void
    {
        $this->expectException(InvalidTotpCode::class);

        TotpCode::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'five digits' => ['12345'];
        yield 'seven digits' => ['1234567'];
        yield 'letters' => ['12a456'];
        yield 'empty' => [''];
    }
}
