<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class CatalogApiRequiresLoginTest extends CatalogApiTestCase
{
    protected function logsInBeforeEachTest(): bool
    {
        return false;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function catalogRoutes(): iterable
    {
        yield 'search' => ['/api/products?q=leche'];
        yield 'product detail' => ['/api/products/'.self::id(1)];
        yield 'price history' => ['/api/products/'.self::id(1).'/price-history'];
        yield 'supermarkets' => ['/api/supermarkets'];
    }

    #[Test]
    #[DataProvider('catalogRoutes')]
    public function it_answers_401_without_a_session(string $uri): void
    {
        $this->seed(1, 'Leche entera');

        $this->client->request('GET', $uri);

        $this->assertProblem(401);
    }

    #[Test]
    #[DataProvider('catalogRoutes')]
    public function it_answers_401_after_the_password_step_only(string $uri): void
    {
        $this->seed(1, 'Leche entera');
        $this->activeUser('maria');
        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(202);

        $this->client->request('GET', $uri);

        $this->assertProblem(401);
    }
}
