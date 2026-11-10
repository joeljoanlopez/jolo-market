<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\ValueObject\SetupCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SetupCodeTest extends TestCase
{
    #[Test]
    public function it_stores_only_the_hash_of_the_normalized_code(): void
    {
        $code = SetupCode::issue('ABCD-EFGH-JKMN-PQRS', new \DateTimeImmutable('2026-11-09 08:00:00'));

        self::assertSame(hash('sha256', 'ABCDEFGHJKMNPQRS'), $code->hash());
        self::assertEquals(new \DateTimeImmutable('2026-11-09 08:00:00'), $code->createdAt());
    }

    #[Test]
    public function it_matches_the_code_whatever_its_case_spaces_or_hyphens(): void
    {
        $code = SetupCode::issue('ABCD-EFGH-JKMN-PQRS', new \DateTimeImmutable());

        self::assertTrue($code->matches('abcd efgh jkmn pqrs'));
        self::assertTrue($code->matches(' ABCDEFGHJKMNPQRS '));
        self::assertFalse($code->matches('ABCD-EFGH-JKMN-PQRT'));
        self::assertFalse($code->matches(''));
    }

    #[Test]
    public function it_is_rebuilt_from_its_stored_hash(): void
    {
        $code = SetupCode::reconstitute(hash('sha256', 'ABCDEFGHJKMNPQRS'), new \DateTimeImmutable('2026-11-09 08:00:00'));

        self::assertTrue($code->matches('ABCD-EFGH-JKMN-PQRS'));
    }
}
