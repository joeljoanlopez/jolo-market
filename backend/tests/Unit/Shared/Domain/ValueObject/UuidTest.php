<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    #[Test]
    public function it_is_created_from_a_canonical_string(): void
    {
        self::assertSame(
            '0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f',
            Uuid::fromString('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f')->value(),
        );
    }

    #[Test]
    public function it_normalizes_to_lowercase(): void
    {
        self::assertSame(
            '0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f',
            Uuid::fromString('0192F3A4-7B1C-7D2E-8F30-4A5B6C7D8E9F')->value(),
        );
    }

    #[Test]
    #[DataProvider('malformedUuids')]
    public function it_rejects_a_malformed_string(string $malformed): void
    {
        $this->expectException(InvalidArgument::class);

        Uuid::fromString($malformed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedUuids(): iterable
    {
        yield 'empty' => [''];
        yield 'no hyphens' => ['0192f3a47b1c7d2e8f304a5b6c7d8e9f'];
        yield 'too short' => ['0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9'];
        yield 'non-hex character' => ['0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9g'];
        yield 'surrounding spaces' => [' 0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f '];
        yield 'braces' => ['{0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f}'];
    }

    #[Test]
    public function it_compares_by_value(): void
    {
        $uuid = Uuid::fromString('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f');

        self::assertTrue($uuid->equals(Uuid::fromString('0192F3A4-7B1C-7D2E-8F30-4A5B6C7D8E9F')));
        self::assertFalse($uuid->equals(Uuid::fromString('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e90')));
    }
}
