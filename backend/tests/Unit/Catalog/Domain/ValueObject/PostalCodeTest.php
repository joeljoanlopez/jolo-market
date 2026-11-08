<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\PostalCode;
use App\Shared\Domain\Exception\InvalidArgument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PostalCodeTest extends TestCase
{
    #[Test]
    public function it_holds_a_spanish_postal_code(): void
    {
        self::assertSame('08001', PostalCode::fromString('08001')->value());
    }

    #[Test]
    public function it_ignores_surrounding_whitespace(): void
    {
        self::assertSame('46001', PostalCode::fromString(' 46001 ')->value());
    }

    #[Test]
    #[DataProvider('malformedPostalCodes')]
    public function it_rejects_a_value_that_is_not_a_spanish_postal_code(string $malformed): void
    {
        $this->expectException(InvalidArgument::class);

        PostalCode::fromString($malformed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedPostalCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'four digits' => ['8001'];
        yield 'six digits' => ['080011'];
        yield 'letters' => ['08A01'];
        yield 'province 00 does not exist' => ['00123'];
        yield 'province 53 does not exist' => ['53001'];
    }

    #[Test]
    public function it_compares_by_value(): void
    {
        self::assertTrue(PostalCode::fromString('08001')->equals(PostalCode::fromString('08001')));
        self::assertFalse(PostalCode::fromString('08001')->equals(PostalCode::fromString('08002')));
    }
}
