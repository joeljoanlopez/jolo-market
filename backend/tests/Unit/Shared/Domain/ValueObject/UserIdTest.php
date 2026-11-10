<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    #[Test]
    public function it_keeps_a_valid_uuid_normalized_to_lowercase(): void
    {
        self::assertSame('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f', UserId::fromString('0192F3A4-7B1C-7D2E-8F30-4A5B6C7D8E9F')->value());
    }

    #[Test]
    public function it_rejects_a_malformed_id(): void
    {
        $this->expectException(InvalidUuid::class);

        UserId::fromString('not-a-uuid');
    }

    #[Test]
    public function it_equals_another_id_with_the_same_value(): void
    {
        $id = UserId::fromString('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f');

        self::assertTrue($id->equals(UserId::fromString('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e9f')));
        self::assertFalse($id->equals(UserId::fromString('0192f3a4-7b1c-7d2e-8f30-4a5b6c7d8e90')));
    }
}
