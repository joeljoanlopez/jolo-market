<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Http;

use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\Tests\Support\Identity\AuthenticatedApiClient;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The test environment enables "mercadona" and "aldi", in that order (backend/.env.test).
 * Catalog rows and users are deleted before each test; the seeded supermarkets are kept.
 * The catalog requires a fully authenticated session, so each test starts logged in as a USER.
 */
abstract class CatalogApiTestCase extends WebTestCase
{
    use AuthenticatedApiClient;

    protected KernelBrowser $client;

    private Connection $connection;

    private int $nextExternalId = 1;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;

        $this->connection->executeStatement('DELETE FROM price_snapshot');
        $this->connection->executeStatement('DELETE FROM product');
        $this->connection->executeStatement('DELETE FROM ingestion_run');
        $this->resetIdentity();

        if ($this->logsInBeforeEachTest()) {
            $this->logInAsNewUser();
        }
    }

    protected function logsInBeforeEachTest(): bool
    {
        return true;
    }

    protected function seed(
        int $number,
        string $name,
        ?string $brand = null,
        string $supermarket = 'mercadona',
        int $priceCents = 100,
        ?int $unitPriceCents = 100,
        ?string $unit = 'l',
        ?int $packageQuantity = 1000,
        ?string $packageUnit = 'l',
        ?string $imageUrl = null,
        bool $available = true,
    ): void {
        $this->connection->insert('product', [
            'id' => BinaryUuid::toDatabase(self::id($number)),
            'supermarket_id' => $supermarket,
            'external_id' => (string) $this->nextExternalId++,
            'name' => $name,
            'brand' => $brand,
            'image_url' => $imageUrl,
            'package_quantity' => $packageQuantity,
            'package_unit' => $packageUnit,
            'price_cents' => $priceCents,
            'unit_price_cents' => $unitPriceCents,
            'unit_price_unit' => $unit,
            'available' => $available,
            'last_seen_at' => '2026-11-08 06:00:00',
        ], ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN]);
    }

    protected static function id(int $number): string
    {
        return \sprintf('00000000-0000-4000-8000-%012d', $number);
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

    protected function assertProblem(int $status): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame($status, $this->json()['status'] ?? null);
    }
}
