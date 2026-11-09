<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Id;

use App\Shared\Infrastructure\Id\UuidV7Generator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UuidV7GeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_version_7_uuids(): void
    {
        $uuid = new UuidV7Generator()->generate()->value();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
    }

    #[Test]
    public function it_generates_ids_that_sort_in_creation_order(): void
    {
        $generator = new UuidV7Generator();

        $ids = array_map(static fn (): string => $generator->generate()->value(), range(1, 100));

        $sorted = $ids;
        sort($sorted);
        self::assertSame($ids, $sorted);
        self::assertCount(100, array_unique($ids));
    }
}
