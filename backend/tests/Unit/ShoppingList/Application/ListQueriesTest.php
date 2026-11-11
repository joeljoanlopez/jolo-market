<?php

declare(strict_types=1);

namespace App\Tests\Unit\ShoppingList\Application;

use App\ShoppingList\Application\EditItems\UpdateItemCommand;
use App\ShoppingList\Application\EditItems\UpdateItemHandler;
use App\ShoppingList\Application\GetList\GetListHandler;
use App\ShoppingList\Application\GetList\GetListQuery;
use App\ShoppingList\Application\GetList\ListItemView;
use App\ShoppingList\Application\GetList\ListProductView;
use App\ShoppingList\Application\GetList\ListUserView;
use App\ShoppingList\Application\GetList\ListView;
use App\ShoppingList\Application\GetList\SupermarketTotal;
use App\ShoppingList\Application\GetMyLists\GetMyListsHandler;
use App\ShoppingList\Application\GetMyLists\GetMyListsQuery;
use App\ShoppingList\Application\GetMyLists\MyListSummary;
use App\ShoppingList\Application\ManageList\RenameListCommand;
use App\ShoppingList\Application\ManageList\RenameListHandler;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;
use App\Tests\Support\ShoppingList\InMemoryProductCatalog;
use App\Tests\Support\ShoppingList\SequentialShareTokenGenerator;
use PHPUnit\Framework\Attributes\Test;

final class ListQueriesTest extends ShoppingListHandlersTestCase
{
    #[Test]
    public function the_owner_sees_the_list_with_its_link_members_and_items_in_order(): void
    {
        $this->catalog->add(self::MILK, 'Leche entera', 125, brand: 'Hacendado', imageUrl: 'https://example.com/milk.jpg');
        $listId = $this->sharedList();
        $bread = $this->addText($listId, 'pan', quantity: 2);
        $milk = $this->addProduct($listId, self::MILK, self::JOAN);
        $this->check($listId, $milk);

        $view = $this->getList(self::MARIA, $listId);

        self::assertSame($listId, $view->id);
        self::assertSame('Compra semanal', $view->name);
        self::assertSame('owner', $view->role);
        self::assertSame($this->list($listId)->version(), $view->version);
        self::assertSame(SequentialShareTokenGenerator::nth(1), $view->shareToken);
        self::assertEquals(new ListUserView(self::MARIA, 'maria'), $view->owner);
        self::assertEquals([new ListUserView(self::JOAN, 'joan')], $view->members);
        self::assertEquals([
            new ListItemView($bread, 0, 2, false, 'text', 'pan', null),
            new ListItemView($milk, 1, 1, true, 'product', null, new ListProductView(self::MILK, 'Leche entera', 'Hacendado', 'mercadona', 125, 'https://example.com/milk.jpg', true)),
        ], $view->items);
    }

    #[Test]
    public function a_member_sees_the_list_without_its_link(): void
    {
        $listId = $this->sharedList();

        $view = $this->getList(self::JOAN, $listId);

        self::assertSame('member', $view->role);
        self::assertNull($view->shareToken);
    }

    #[Test]
    public function an_outsider_is_told_the_list_does_not_exist(): void
    {
        $listId = $this->sharedList();

        $this->expectException(ShoppingListNotFound::class);

        $this->getList(self::PEDRO, $listId);
    }

    #[Test]
    public function an_unknown_list_is_not_found(): void
    {
        $this->expectException(ShoppingListNotFound::class);

        $this->getList(self::MARIA, 'not-a-uuid');
    }

    #[Test]
    public function totals_add_price_times_quantity_of_available_products_per_supermarket_ordered_by_name(): void
    {
        $this->catalog->add(self::MILK, 'Leche entera', 125, 'mercadona');
        $this->catalog->add(self::WATER, 'Agua', 30, 'mercadona');
        $this->catalog->add(self::BREAD, 'Pan', 89, 'aldi');
        $this->catalog->add(self::GONE, 'Leche desnatada', 99, 'bonpreu');
        $listId = $this->createList();
        $this->addText($listId, 'servilletas', quantity: 3);
        $milk = $this->addProduct($listId, self::MILK, quantity: 2);
        $this->addProduct($listId, self::WATER, quantity: 6);
        $this->addProduct($listId, self::BREAD);
        $this->addProduct($listId, self::GONE);
        $this->catalog->add(self::GONE, 'Leche desnatada', 99, 'bonpreu', available: false);
        $this->check($listId, $milk);

        $view = $this->getList(self::MARIA, $listId);

        self::assertEquals([
            new SupermarketTotal('aldi', 'Aldi', 89, 1),
            new SupermarketTotal('mercadona', 'Mercadona', 2 * 125 + 6 * 30, 2),
        ], $view->totals);
    }

    #[Test]
    public function an_unavailable_product_is_shown_flagged_and_left_out_of_the_totals(): void
    {
        $this->catalog->add(self::GONE, 'Leche desnatada', 99, 'bonpreu');
        $listId = $this->createList();
        $gone = $this->addProduct($listId, self::GONE);
        $this->catalog->add(self::GONE, 'Leche desnatada', 99, 'bonpreu', available: false);

        $view = $this->getList(self::MARIA, $listId);

        self::assertEquals(
            [new ListItemView($gone, 0, 1, false, 'product', null, new ListProductView(self::GONE, 'Leche desnatada', null, 'bonpreu', 99, null, false))],
            $view->items,
        );
        self::assertSame([], $view->totals);
    }

    #[Test]
    public function a_product_the_catalog_no_longer_knows_is_shown_unavailable_without_a_name(): void
    {
        $this->catalog->add(self::WATER, 'Agua', 30);
        $listId = $this->createList();
        $water = $this->addProduct($listId, self::WATER);
        $this->catalog = new InMemoryProductCatalog();

        $view = $this->getList(self::MARIA, $listId);

        self::assertEquals(
            [new ListItemView($water, 0, 1, false, 'product', null, new ListProductView(self::WATER, null, null, null, null, null, false))],
            $view->items,
        );
        self::assertSame([], $view->totals);
    }

    #[Test]
    public function my_lists_are_the_lists_i_own_or_joined_most_recently_updated_first(): void
    {
        $mine = $this->createList(self::MARIA, 'Mía');
        $this->clock->advance(new \DateInterval('PT1H'));
        $joans = $this->createList(self::JOAN, 'De Joan');
        $this->join(self::MARIA, $this->list($joans)->shareToken()->value());
        $this->createList(self::PEDRO, 'De Pedro');
        $this->clock->advance(new \DateInterval('PT1H'));
        $this->addText($mine, 'pan');
        $this->addText($mine, 'huevos');

        $lists = (new GetMyListsHandler($this->lists, $this->directory))(new GetMyListsQuery(self::MARIA));

        self::assertEquals([
            new MyListSummary($mine, 'Mía', 'owner', 'maria', 2, self::at('2026-11-11 12:00:00')),
            new MyListSummary($joans, 'De Joan', 'member', 'joan', 0, self::at('2026-11-11 11:00:00')),
        ], $lists);
    }

    #[Test]
    public function a_user_with_no_lists_has_none(): void
    {
        $this->createList(self::MARIA);

        self::assertSame([], (new GetMyListsHandler($this->lists, $this->directory))(new GetMyListsQuery(self::PEDRO)));
    }

    #[Test]
    public function the_version_moves_with_every_change(): void
    {
        $listId = $this->createList();
        $before = $this->getList(self::MARIA, $listId)->version;

        (new RenameListHandler($this->updater, $this->clock))(new RenameListCommand(self::MARIA, $listId, 'Otra'));

        self::assertSame($before + 1, $this->getList(self::MARIA, $listId)->version);
    }

    private function getList(string $actor, string $listId): ListView
    {
        return (new GetListHandler($this->updater, $this->catalog, $this->directory))(new GetListQuery($actor, $listId));
    }

    private function check(string $listId, string $itemId): void
    {
        (new UpdateItemHandler($this->updater, $this->clock))(new UpdateItemCommand(self::MARIA, $listId, $itemId, checked: true));
    }
}
