<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Infrastructure\Http;

use App\Shared\Application\Health\DatabaseConnectivity;
use App\Tests\Unit\Shared\Application\Health\FakeDatabaseConnectivity;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    #[Test]
    public function it_returns_200_with_status_ok_when_the_database_is_reachable(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('{"status":"ok"}', $client->getResponse()->getContent());
    }

    #[Test]
    public function it_returns_503_with_status_unavailable_when_the_database_is_unreachable(): void
    {
        $client = self::createClient();
        self::getContainer()->set(DatabaseConnectivity::class, new FakeDatabaseConnectivity(reachable: false));

        $client->request('GET', '/api/health');

        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('{"status":"unavailable"}', $client->getResponse()->getContent());
    }
}
