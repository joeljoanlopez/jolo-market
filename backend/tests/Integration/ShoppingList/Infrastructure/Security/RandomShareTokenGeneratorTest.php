<?php

declare(strict_types=1);

namespace App\Tests\Integration\ShoppingList\Infrastructure\Security;

use App\ShoppingList\Infrastructure\Security\RandomShareTokenGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RandomShareTokenGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_url_safe_tokens_of_256_random_bits(): void
    {
        $token = (new RandomShareTokenGenerator())->generate()->value();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $token);
        $bytes = base64_decode(strtr($token, '-_', '+/'), true);
        self::assertIsString($bytes);
        self::assertSame(32, \strlen($bytes));
    }

    #[Test]
    public function every_token_is_different(): void
    {
        $generator = new RandomShareTokenGenerator();

        $tokens = array_map(static fn (): string => $generator->generate()->value(), range(1, 100));

        self::assertCount(100, array_unique($tokens));
    }
}
