<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProblemJsonErrorTest extends WebTestCase
{
    #[Test]
    public function it_answers_an_unknown_route_with_a_404_problem_json(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/this-route-does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    #[Test]
    public function it_answers_a_disallowed_method_with_a_405_problem_json(): void
    {
        $client = self::createClient();

        $client->request('DELETE', '/api/health');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertResponseHeaderSame('Allow', 'GET');
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Method Not Allowed', 'status' => 405],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }
}
