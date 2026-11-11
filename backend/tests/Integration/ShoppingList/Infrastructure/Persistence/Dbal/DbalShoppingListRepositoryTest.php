<?php

declare(strict_types=1);

namespace App\Tests\Integration\ShoppingList\Infrastructure\Persistence\Dbal;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Domain\Model\FreeTextItem;
use App\ShoppingList\Domain\Model\ListItem;
use App\ShoppingList\Domain\Model\Member;
use App\ShoppingList\Domain\Model\ProductItem;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShoppingListChangedConcurrently;
use App\ShoppingList\Domain\Port\ShoppingListRepository;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\ProductId;
use App\ShoppingList\Domain\ValueObject\Quantity;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use App\ShoppingList\Infrastructure\Persistence\Dbal\DbalShoppingListRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalShoppingListRepositoryTest extends KernelTestCase
{
    private const string LIST = '0193aaaa-0000-7000-8000-000000000001';
    private const string OTHER_LIST = '0193aaaa-0000-7000-8000-000000000002';
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string JOAN = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000003';
    private const string MILK = '0191bbbb-0000-7000-8000-000000000001';
    private const string TOKEN = 'kq3-Zx9_vQm3kT8wLr2Nf6Yb1Hc4Pj7Sd0Ga5Ue8Wq2';
    private const string OTHER_TOKEN = 'Zx9vQm3kT8wLr2Nf6Yb1Hc4Pj7Sd0Ga5Ue8Wq2Ki3Mo';

    private Connection $connection;
    private DbalShoppingListRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
        $this->connection->executeStatement('DELETE FROM shopping_list');
        $this->repository = new DbalShoppingListRepository($this->connection);
    }

    #[Test]
    public function it_is_the_repository_the_application_gets(): void
    {
        self::assertInstanceOf(DbalShoppingListRepository::class, self::getContainer()->get(ShoppingListRepository::class));
    }

    #[Test]
    public function it_saves_and_reloads_the_whole_list_with_members_and_items_in_order(): void
    {
        $list = $this->newList();
        $list->join(self::user(self::JOAN), self::at('11:00:00.123456'));
        $list->join(self::user(self::PEDRO), self::at('11:30'));
        $list->addFreeTextItem(self::user(self::MARIA), self::item(1), ItemText::fromString('pan de ñandú'), Quantity::of(2), self::at('12:00'));
        $list->addProductItem(self::user(self::JOAN), self::item(2), ProductId::fromString(self::MILK), Quantity::of(99), self::at('12:00'));
        $list->addFreeTextItem(self::user(self::MARIA), self::item(3), ItemText::fromString('huevos'), Quantity::default(), self::at('12:00'));
        $list->check(self::user(self::MARIA), self::item(2), self::at('12:00'));
        $list->moveItem(self::user(self::MARIA), self::item(3), 0, self::at('12:30'));

        $this->repository->save($list);
        $found = $this->find(self::LIST);

        self::assertSame(self::LIST, $found->id()->value());
        self::assertTrue($found->ownerId()->equals(self::user(self::MARIA)));
        self::assertSame('Compra semanal', $found->name()->value());
        self::assertSame(self::TOKEN, $found->shareToken()->value());
        self::assertEquals([new Member(self::user(self::JOAN), self::at('11:00:00.123456')), new Member(self::user(self::PEDRO), self::at('11:30'))], $found->members());
        self::assertEquals([
            ListItem::reconstitute(self::item(3), new FreeTextItem(ItemText::fromString('huevos')), Quantity::default(), false),
            ListItem::reconstitute(self::item(1), new FreeTextItem(ItemText::fromString('pan de ñandú')), Quantity::of(2), false),
            ListItem::reconstitute(self::item(2), new ProductItem(ProductId::fromString(self::MILK)), Quantity::of(99), true),
        ], $found->items());
        self::assertEquals(self::at('10:00'), $found->createdAt());
        self::assertEquals(self::at('12:30'), $found->updatedAt());
        self::assertSame($list->version(), $found->version());
        self::assertSame($list->version(), $found->loadedVersion());
    }

    #[Test]
    public function it_saves_the_changes_of_a_loaded_list(): void
    {
        $list = $this->newList();
        $list->join(self::user(self::JOAN), self::at('11:00'));
        $list->addFreeTextItem(self::user(self::MARIA), self::item(1), ItemText::fromString('pan'), Quantity::default(), self::at('11:00'));
        $list->addFreeTextItem(self::user(self::MARIA), self::item(2), ItemText::fromString('sal'), Quantity::default(), self::at('11:00'));
        $this->repository->save($list);

        $loaded = $this->find(self::LIST);
        $loaded->rename(self::user(self::MARIA), ListName::fromString('Fiesta'), self::at('12:00'));
        $loaded->regenerateShareToken(self::user(self::MARIA), ShareToken::fromString(self::OTHER_TOKEN), self::at('12:00'));
        $loaded->leave(self::user(self::JOAN), self::at('12:00'));
        $loaded->removeItem(self::user(self::MARIA), self::item(1), self::at('12:00'));
        $loaded->changeQuantity(self::user(self::MARIA), self::item(2), Quantity::of(5), self::at('12:00'));
        $this->repository->save($loaded);

        $found = $this->find(self::LIST);
        self::assertSame('Fiesta', $found->name()->value());
        self::assertSame(self::OTHER_TOKEN, $found->shareToken()->value());
        self::assertSame([], $found->members());
        self::assertCount(1, $found->items());
        self::assertSame(5, $found->items()[0]->quantity()->value());
        self::assertSame($loaded->version(), $found->version());
    }

    #[Test]
    public function saving_a_copy_loaded_before_someone_else_saved_fails_and_changes_nothing(): void
    {
        $this->repository->save($this->newList());
        $mine = $this->find(self::LIST);
        $theirs = $this->find(self::LIST);
        $theirs->rename(self::user(self::MARIA), ListName::fromString('Suya'), self::at('11:00'));
        $this->repository->save($theirs);

        $mine->rename(self::user(self::MARIA), ListName::fromString('Mía'), self::at('11:00'));
        $mine->addFreeTextItem(self::user(self::MARIA), self::item(1), ItemText::fromString('pan'), Quantity::default(), self::at('11:00'));
        try {
            $this->repository->save($mine);
            self::fail('Expected a ShoppingListChangedConcurrently.');
        } catch (ShoppingListChangedConcurrently) {
        }

        $found = $this->find(self::LIST);
        self::assertSame('Suya', $found->name()->value());
        self::assertSame([], $found->items());
    }

    #[Test]
    public function saving_a_list_someone_else_deleted_fails(): void
    {
        $this->repository->save($this->newList());
        $loaded = $this->find(self::LIST);
        $this->repository->remove($this->find(self::LIST));
        $loaded->rename(self::user(self::MARIA), ListName::fromString('Mía'), self::at('11:00'));

        $this->expectException(ShoppingListChangedConcurrently::class);

        $this->repository->save($loaded);
    }

    #[Test]
    public function it_removes_a_list_with_its_members_and_items(): void
    {
        $list = $this->newList();
        $list->join(self::user(self::JOAN), self::at('11:00'));
        $list->addFreeTextItem(self::user(self::MARIA), self::item(1), ItemText::fromString('pan'), Quantity::default(), self::at('11:00'));
        $this->repository->save($list);

        $this->repository->remove($this->find(self::LIST));

        self::assertNull($this->repository->findById(ShoppingListId::fromString(self::LIST)));
        self::assertSame(0, $this->countRows('list_member'));
        self::assertSame(0, $this->countRows('list_item'));
    }

    #[Test]
    public function it_finds_a_list_by_its_share_token_only(): void
    {
        $this->repository->save($this->newList());

        self::assertSame(self::LIST, $this->repository->findByShareToken(ShareToken::fromString(self::TOKEN))?->id()->value());
        self::assertNull($this->repository->findByShareToken(ShareToken::fromString(self::OTHER_TOKEN)));
        self::assertNull($this->repository->findByShareToken(ShareToken::fromString(strtolower(self::TOKEN))));
    }

    #[Test]
    public function it_finds_the_lists_a_user_owns_or_joined(): void
    {
        $this->repository->save($this->newList());
        $joans = ShoppingList::create(ShoppingListId::fromString(self::OTHER_LIST), self::user(self::JOAN), ListName::fromString('De Joan'), ShareToken::fromString(self::OTHER_TOKEN), self::at('10:00'));
        $joans->join(self::user(self::MARIA), self::at('11:00'));
        $this->repository->save($joans);

        $mariaLists = self::ids($this->repository->findByUser(self::user(self::MARIA)));
        sort($mariaLists);

        self::assertSame([self::LIST, self::OTHER_LIST], $mariaLists);
        self::assertSame([self::OTHER_LIST], self::ids($this->repository->findByUser(self::user(self::JOAN))));
        self::assertSame([], $this->repository->findByUser(self::user(self::PEDRO)));
    }

    private function newList(): ShoppingList
    {
        return ShoppingList::create(
            ShoppingListId::fromString(self::LIST),
            self::user(self::MARIA),
            ListName::fromString('Compra semanal'),
            ShareToken::fromString(self::TOKEN),
            self::at('10:00'),
        );
    }

    private function find(string $id): ShoppingList
    {
        return $this->repository->findById(ShoppingListId::fromString($id)) ?? self::fail('List not found.');
    }

    private function countRows(string $table): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    /**
     * @param list<ShoppingList> $lists
     *
     * @return list<string>
     */
    private static function ids(array $lists): array
    {
        return array_map(static fn (ShoppingList $list): string => $list->id()->value(), $lists);
    }

    private static function item(int $n): ListItemId
    {
        return ListItemId::fromString(\sprintf('0193cccc-0000-7000-8000-%012d', $n));
    }

    private static function user(string $id): UserId
    {
        return UserId::fromString($id);
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-11 '.$time, new \DateTimeZone('UTC'));
    }
}
