<?php

declare(strict_types=1);

namespace App\Tests\Functional\ShoppingList\Infrastructure\Api;

use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Dbal\BinaryUuid;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShoppingListRepository;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\Quantity;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use App\Tests\Support\Identity\AuthenticatedApiClient;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Lists, catalog products and users are deleted before each test. Maria, Joan and Pedro are
 * active users; tests store lists through the real repository and then act as one of them.
 */
abstract class ShoppingListApiTestCase extends WebTestCase
{
    use AuthenticatedApiClient;

    protected const string LIST = '0193aaaa-0000-7000-8000-000000000001';
    protected const string UNKNOWN = '0193aaaa-0000-7000-8000-000000000099';
    protected const string TOKEN = 'kq3-Zx9_vQm3kT8wLr2Nf6Yb1Hc4Pj7Sd0Ga5Ue8Wq2';

    protected KernelBrowser $client;

    protected string $maria;
    protected string $joan;
    protected string $pedro;

    private int $nextItem = 1;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->db()->executeStatement('DELETE FROM shopping_list');
        $this->db()->executeStatement('DELETE FROM price_snapshot');
        $this->db()->executeStatement('DELETE FROM product');
        $this->resetIdentity();

        $this->maria = $this->activeUser('maria')->id()->value();
        $this->joan = $this->activeUser('joan')->id()->value();
        $this->pedro = $this->activeUser('pedro')->id()->value();
    }

    /**
     * Logs the client in as another user, from scratch.
     */
    protected function actAs(string $username): void
    {
        $this->client->getCookieJar()->clear();
        $this->logIn($username);
    }

    /**
     * Maria's list "Compra semanal", which Joan joined.
     */
    protected function storeSharedList(): ShoppingList
    {
        $list = ShoppingList::create(
            ShoppingListId::fromString(self::LIST),
            UserId::fromString($this->maria),
            ListName::fromString('Compra semanal'),
            ShareToken::fromString(self::TOKEN),
            new \DateTimeImmutable('2026-11-11 10:00:00'),
        );
        $list->join(UserId::fromString($this->joan), new \DateTimeImmutable('2026-11-11 10:05:00'));
        $this->lists()->save($list);

        return $this->storedList();
    }

    protected function storeTextItem(string $text, bool $checked = false): string
    {
        $list = $this->storedList();
        $id = ListItemId::fromString(\sprintf('0193cccc-0000-7000-8000-%012d', $this->nextItem++));
        $list->addFreeTextItem($list->ownerId(), $id, ItemText::fromString($text), Quantity::default(), new \DateTimeImmutable());
        if ($checked) {
            $list->check($list->ownerId(), $id, new \DateTimeImmutable());
        }
        $this->lists()->save($list);

        return $id->value();
    }

    protected function storedList(string $id = self::LIST): ShoppingList
    {
        return $this->lists()->findById(ShoppingListId::fromString($id)) ?? self::fail('The list is not stored.');
    }

    protected function listExists(string $id = self::LIST): bool
    {
        return null !== $this->lists()->findById(ShoppingListId::fromString($id));
    }

    protected function seedProduct(int $number, string $name, int $priceCents, string $supermarket = 'mercadona', bool $available = true, ?string $brand = null): string
    {
        $id = \sprintf('0191bbbb-0000-7000-8000-%012d', $number);
        $this->db()->insert('product', [
            'id' => BinaryUuid::toDatabase($id),
            'supermarket_id' => $supermarket,
            'external_id' => (string) $number,
            'name' => $name,
            'brand' => $brand,
            'image_url' => null,
            'package_quantity' => null,
            'package_unit' => null,
            'price_cents' => $priceCents,
            'unit_price_cents' => null,
            'unit_price_unit' => null,
            'available' => $available,
            'last_seen_at' => '2026-11-08 06:00:00',
        ], ['id' => ParameterType::BINARY, 'available' => ParameterType::BOOLEAN]);

        return $id;
    }

    protected function markUnavailable(string $productId): void
    {
        $this->db()->update('product', ['available' => false], ['id' => BinaryUuid::toDatabase($productId)], ['available' => ParameterType::BOOLEAN, 'id' => ParameterType::BINARY]);
    }

    protected function deleteAllProducts(): void
    {
        $this->db()->executeStatement('DELETE FROM product');
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function send(string $method, string $uri, ?array $body = null): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );
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
    }

    private function lists(): ShoppingListRepository
    {
        $lists = self::getContainer()->get('test.shopping_list.repository');
        self::assertInstanceOf(ShoppingListRepository::class, $lists);

        return $lists;
    }

    private function db(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
