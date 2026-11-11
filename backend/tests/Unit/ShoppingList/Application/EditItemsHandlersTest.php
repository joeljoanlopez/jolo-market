<?php

declare(strict_types=1);

namespace App\Tests\Unit\ShoppingList\Application;

use App\ShoppingList\Application\EditItems\AddItemCommand;
use App\ShoppingList\Application\EditItems\ClearCheckedItemsCommand;
use App\ShoppingList\Application\EditItems\ClearCheckedItemsHandler;
use App\ShoppingList\Application\EditItems\InvalidItemType;
use App\ShoppingList\Application\EditItems\ProductNotAvailable;
use App\ShoppingList\Application\EditItems\RemoveItemCommand;
use App\ShoppingList\Application\EditItems\RemoveItemHandler;
use App\ShoppingList\Application\EditItems\UpdateItemCommand;
use App\ShoppingList\Application\EditItems\UpdateItemHandler;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\FreeTextItem;
use App\ShoppingList\Domain\Model\InvalidItemPosition;
use App\ShoppingList\Domain\Model\ListItem;
use App\ShoppingList\Domain\Model\ListItemNotFound;
use App\ShoppingList\Domain\Model\ProductItem;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;
use App\ShoppingList\Domain\Port\ShoppingListChangedConcurrently;
use App\ShoppingList\Domain\ValueObject\InvalidItemText;
use App\ShoppingList\Domain\ValueObject\InvalidQuantity;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\ProductId;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use App\Tests\Support\ShoppingList\InMemoryShoppingListRepository;
use PHPUnit\Framework\Attributes\Test;

final class EditItemsHandlersTest extends ShoppingListHandlersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog->add(self::MILK, 'Leche entera', 125);
        $this->catalog->add(self::GONE, 'Leche desnatada', 99, available: false);
    }

    #[Test]
    public function owner_and_members_add_free_text_items_with_a_quantity_of_1_by_default(): void
    {
        $listId = $this->sharedList();

        $bread = $this->addText($listId, ' pan ');
        $eggs = $this->addText($listId, 'huevos', self::JOAN, 12);

        $items = $this->list($listId)->items();
        self::assertSame([$bread, $eggs], array_map(static fn (ListItem $item): string => $item->id()->value(), $items));
        self::assertEquals(new FreeTextItem(ItemText::fromString('pan')), $items[0]->content());
        self::assertSame(1, $items[0]->quantity()->value());
        self::assertSame(12, $items[1]->quantity()->value());
    }

    #[Test]
    public function an_outsider_cannot_add_items(): void
    {
        $listId = $this->sharedList();

        $this->expectException(ShoppingListNotFound::class);

        $this->addText($listId, 'pan', self::PEDRO);
    }

    #[Test]
    public function a_free_text_item_needs_a_valid_text(): void
    {
        $listId = $this->createList();

        $this->expectException(InvalidItemText::class);

        $this->addText($listId, '   ');
    }

    #[Test]
    public function a_free_text_item_without_text_is_rejected(): void
    {
        $listId = $this->createList();

        $this->expectException(InvalidItemText::class);

        $this->addItem()(new AddItemCommand(self::MARIA, $listId, 'text'));
    }

    #[Test]
    public function a_quantity_outside_1_to_99_is_rejected(): void
    {
        $listId = $this->createList();

        $this->expectException(InvalidQuantity::class);

        $this->addText($listId, 'pan', quantity: 100);
    }

    #[Test]
    public function an_unknown_item_type_is_rejected(): void
    {
        $listId = $this->createList();

        $this->expectException(InvalidItemType::class);

        $this->addItem()(new AddItemCommand(self::MARIA, $listId, 'recipe', text: 'pan'));
    }

    #[Test]
    public function an_available_product_is_added_as_a_product_item(): void
    {
        $listId = $this->createList();

        $milk = $this->addProduct($listId, self::MILK, quantity: 2);

        $item = $this->list($listId)->items()[0];
        self::assertSame($milk, $item->id()->value());
        self::assertEquals(new ProductItem(ProductId::fromString(self::MILK)), $item->content());
        self::assertSame(2, $item->quantity()->value());
    }

    #[Test]
    public function adding_a_product_already_in_the_list_adds_to_its_quantity(): void
    {
        $listId = $this->createList();
        $milk = $this->addProduct($listId, self::MILK, quantity: 2);

        self::assertSame($milk, $this->addProduct($listId, self::MILK));

        self::assertCount(1, $this->list($listId)->items());
        self::assertSame(3, $this->list($listId)->items()[0]->quantity()->value());
    }

    #[Test]
    public function an_unknown_unavailable_malformed_or_missing_product_is_rejected(): void
    {
        $listId = $this->createList();

        foreach ([self::WATER, self::GONE, 'not-a-uuid', null] as $productId) {
            try {
                $this->addItem()(new AddItemCommand(self::MARIA, $listId, 'product', productId: $productId));
                self::fail('Expected a ProductNotAvailable.');
            } catch (ProductNotAvailable) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame([], $this->list($listId)->items());
    }

    #[Test]
    public function updating_an_item_changes_quantity_checked_and_position_together(): void
    {
        $listId = $this->sharedList();
        $bread = $this->addText($listId, 'pan');
        $eggs = $this->addText($listId, 'huevos');

        ($this->updateItem())(new UpdateItemCommand(self::JOAN, $listId, $eggs, quantity: 6, checked: true, position: 0));

        $items = $this->list($listId)->items();
        self::assertSame([$eggs, $bread], array_map(static fn (ListItem $item): string => $item->id()->value(), $items));
        self::assertSame(6, $items[0]->quantity()->value());
        self::assertTrue($items[0]->isChecked());
    }

    #[Test]
    public function updating_only_some_fields_leaves_the_others_alone(): void
    {
        $listId = $this->createList();
        $bread = $this->addText($listId, 'pan', quantity: 3);

        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, checked: true));
        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, checked: false));

        $item = $this->list($listId)->items()[0];
        self::assertSame(3, $item->quantity()->value());
        self::assertFalse($item->isChecked());
    }

    #[Test]
    public function checking_twice_keeps_the_item_checked(): void
    {
        $listId = $this->createList();
        $bread = $this->addText($listId, 'pan');

        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, checked: true));
        $version = $this->list($listId)->version();
        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, checked: true));

        self::assertTrue($this->list($listId)->items()[0]->isChecked());
        self::assertSame($version, $this->list($listId)->version());
    }

    #[Test]
    public function updating_an_unknown_or_malformed_item_is_not_found(): void
    {
        $listId = $this->createList();

        foreach (['0193cccc-0000-7000-8000-000000000099', 'not-a-uuid'] as $itemId) {
            try {
                ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $itemId, checked: true));
                self::fail('Expected a ListItemNotFound.');
            } catch (ListItemNotFound) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function updating_with_an_invalid_quantity_or_position_is_rejected(): void
    {
        $listId = $this->createList();
        $bread = $this->addText($listId, 'pan');

        try {
            ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, quantity: 0));
            self::fail('Expected an InvalidQuantity.');
        } catch (InvalidQuantity) {
        }

        $this->expectException(InvalidItemPosition::class);
        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, position: -1));
    }

    #[Test]
    public function an_outsider_cannot_update_items(): void
    {
        $listId = $this->createList();
        $bread = $this->addText($listId, 'pan');

        $this->expectException(ShoppingListNotFound::class);

        ($this->updateItem())(new UpdateItemCommand(self::PEDRO, $listId, $bread, checked: true));
    }

    #[Test]
    public function removing_an_item(): void
    {
        $listId = $this->sharedList();
        $bread = $this->addText($listId, 'pan');

        ($this->removeItem())(new RemoveItemCommand(self::JOAN, $listId, $bread));

        self::assertSame([], $this->list($listId)->items());
    }

    #[Test]
    public function removing_an_unknown_item_is_not_found(): void
    {
        $listId = $this->createList();

        $this->expectException(ListItemNotFound::class);

        ($this->removeItem())(new RemoveItemCommand(self::MARIA, $listId, 'not-a-uuid'));
    }

    #[Test]
    public function an_outsider_cannot_remove_items(): void
    {
        $listId = $this->createList();
        $bread = $this->addText($listId, 'pan');

        $this->expectException(ShoppingListNotFound::class);

        ($this->removeItem())(new RemoveItemCommand(self::PEDRO, $listId, $bread));
    }

    #[Test]
    public function clearing_checked_items(): void
    {
        $listId = $this->sharedList();
        $bread = $this->addText($listId, 'pan');
        $this->addText($listId, 'huevos');
        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, checked: true));

        ($this->clearChecked())(new ClearCheckedItemsCommand(self::JOAN, $listId));

        self::assertCount(1, $this->list($listId)->items());
    }

    #[Test]
    public function an_outsider_cannot_clear_checked_items(): void
    {
        $listId = $this->createList();

        $this->expectException(ShoppingListNotFound::class);

        ($this->clearChecked())(new ClearCheckedItemsCommand(self::PEDRO, $listId));
    }

    #[Test]
    public function edits_to_different_items_saved_at_the_same_time_never_undo_each_other(): void
    {
        $listId = $this->sharedList();
        $bread = $this->addText($listId, 'pan');
        $eggs = $this->addText($listId, 'huevos');
        // Joan checks the bread while Maria's request, which loaded the list before, checks the eggs.
        $this->lists->beforeNextSaves(static function (InMemoryShoppingListRepository $lists) use ($listId, $bread): void {
            $list = $lists->findById(ShoppingListId::fromString($listId)) ?? self::fail('No list.');
            $list->check(self::user(self::JOAN), ListItemId::fromString($bread), self::at('2026-11-11 10:00:00'));
            $lists->save($list);
        });

        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $eggs, checked: true));

        $items = $this->list($listId)->items();
        self::assertTrue($items[0]->isChecked(), 'Joan\'s check was undone.');
        self::assertTrue($items[1]->isChecked(), 'Maria\'s check was lost.');
    }

    #[Test]
    public function an_item_added_while_someone_else_saves_is_added_once_with_its_id(): void
    {
        $listId = $this->createList();
        $this->lists->beforeNextSaves(static function (InMemoryShoppingListRepository $lists) use ($listId): void {
            $list = $lists->findById(ShoppingListId::fromString($listId)) ?? self::fail('No list.');
            $list->rename(self::user(self::MARIA), ListName::fromString('Fiesta'), self::at('2026-11-11 10:00:00'));
            $lists->save($list);
        });

        $bread = $this->addText($listId, 'pan');

        $list = $this->list($listId);
        self::assertSame('Fiesta', $list->name()->value());
        self::assertCount(1, $list->items());
        self::assertSame($bread, $list->items()[0]->id()->value());
    }

    #[Test]
    public function it_gives_up_when_the_list_keeps_changing_under_every_retry(): void
    {
        $listId = $this->createList();
        $bread = $this->addText($listId, 'pan');
        $renameConcurrently = static function (InMemoryShoppingListRepository $lists) use ($listId): void {
            $list = $lists->findById(ShoppingListId::fromString($listId)) ?? self::fail('No list.');
            $list->join(self::user(self::JOAN), self::at('2026-11-11 10:00:00'));
            $list->leave(self::user(self::JOAN), self::at('2026-11-11 10:00:00'));
            $lists->save($list);
        };
        $this->lists->beforeNextSaves(...array_fill(0, ListUpdater::MAX_ATTEMPTS, $renameConcurrently));

        $this->expectException(ShoppingListChangedConcurrently::class);

        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, checked: true));
    }

    #[Test]
    public function a_list_deleted_while_an_edit_retries_is_not_found(): void
    {
        $listId = $this->createList();
        $bread = $this->addText($listId, 'pan');
        $this->lists->beforeNextSaves(static function (InMemoryShoppingListRepository $lists) use ($listId): void {
            $list = $lists->findById(ShoppingListId::fromString($listId)) ?? self::fail('No list.');
            $lists->remove($list);
        });

        $this->expectException(ShoppingListNotFound::class);

        ($this->updateItem())(new UpdateItemCommand(self::MARIA, $listId, $bread, checked: true));
    }

    #[Test]
    public function an_edit_that_changes_nothing_is_not_saved(): void
    {
        $listId = $this->createList();
        $saves = $this->lists->saves();

        ($this->clearChecked())(new ClearCheckedItemsCommand(self::MARIA, $listId));

        self::assertSame($saves, $this->lists->saves());
    }

    private function updateItem(): UpdateItemHandler
    {
        return new UpdateItemHandler($this->updater, $this->clock);
    }

    private function removeItem(): RemoveItemHandler
    {
        return new RemoveItemHandler($this->updater, $this->clock);
    }

    private function clearChecked(): ClearCheckedItemsHandler
    {
        return new ClearCheckedItemsHandler($this->updater, $this->clock);
    }
}
