<?php

declare(strict_types=1);

namespace App\Tests\Support\Dev;

use App\Identity\Domain\Port\SetupCodeRepository;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\SetupCode;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShoppingListRepository;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\Quantity;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use App\Tests\Support\Identity\Users;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rows of every context in the test database, for the development reset and seed tests.
 */
final readonly class DevTables
{
    private const array TABLES = ['list_item', 'list_member', 'shopping_list', 'price_snapshot', 'product', 'ingestion_run', 'setup_code', 'user'];

    private const string USER_ID = '0192f3a4-7b1c-7d2e-8f30-000000000101';

    private function __construct(private ContainerInterface $container)
    {
    }

    public static function of(ContainerInterface $container): self
    {
        return new self($container);
    }

    public function emptyAll(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection()->executeStatement('DELETE FROM '.$table);
        }
    }

    public function rows(string $table): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        if (!is_numeric($count)) {
            throw new \UnexpectedValueException('COUNT(*) did not return a number.');
        }

        return (int) $count;
    }

    /**
     * Stores the user "maria" (not a dummy user) with a list that has one member and one item.
     */
    public function storeUserAndList(): void
    {
        $this->service(UserRepository::class)->save(Users::active(self::USER_ID, 'maria'));
        $now = new \DateTimeImmutable('2026-11-11 10:00:00');
        $owner = UserId::fromString(self::USER_ID);
        $list = ShoppingList::create(
            ShoppingListId::fromString('0193aaaa-0000-7000-8000-000000000001'),
            $owner,
            ListName::fromString('Compra semanal'),
            ShareToken::fromString('dev-reset-share-token-0000000000000000'),
            $now,
        );
        $list->addFreeTextItem($owner, ListItemId::fromString('0193cccc-0000-7000-8000-000000000001'), ItemText::fromString('Pan'), Quantity::default(), $now);
        $this->service(ShoppingListRepository::class)->save($list);
    }

    public function storeSetupCode(): void
    {
        $this->service(SetupCodeRepository::class)->replace(SetupCode::issue('ABCD-EFGH-JKMN-PQRS', new \DateTimeImmutable()));
    }

    private function connection(): Connection
    {
        return $this->service(Connection::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = $this->container->get($id);
        if (!$service instanceof $id) {
            throw new \UnexpectedValueException(\sprintf('The container has no %s.', $id));
        }

        return $service;
    }
}
