<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Api;

use App\Tests\Support\Identity\AuthenticatedApiClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Requests go through the whole stack. Users and rate limits are cleared before each test.
 */
abstract class IdentityApiTestCase extends WebTestCase
{
    use AuthenticatedApiClient;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->resetIdentity();
    }

    /**
     * @return array<mixed>
     */
    protected function json(): array
    {
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
