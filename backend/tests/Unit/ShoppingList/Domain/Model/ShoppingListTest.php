<?php

declare(strict_types=1);

namespace App\Tests\Unit\ShoppingList\Domain\Model;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Domain\Model\FreeTextItem;
use App\ShoppingList\Domain\Model\InvalidItemPosition;
use App\ShoppingList\Domain\Model\ListItem;
use App\ShoppingList\Domain\Model\ListItemNotFound;
use App\ShoppingList\Domain\Model\Member;
use App\ShoppingList\Domain\Model\MemberNotFound;
use App\ShoppingList\Domain\Model\OwnerCannotLeave;
use App\ShoppingList\Domain\Model\ProductItem;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;
use App\ShoppingList\Domain\ValueObject\InvalidQuantity;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\ProductId;
use App\ShoppingList\Domain\ValueObject\Quantity;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShoppingListTest extends TestCase
{
    private const string LIST = '0193aaaa-0000-7000-8000-000000000001';
    private const string MARIA = '0192f3a4-7b1c-7d2e-8f30-000000000001';
    private const string JOAN = '0192f3a4-7b1c-7d2e-8f30-000000000002';
    private const string PEDRO = '0192f3a4-7b1c-7d2e-8f30-000000000003';
    private const string MILK = '0191bbbb-0000-7000-8000-000000000001';
    private const string BREAD = '0191bbbb-0000-7000-8000-000000000002';
    private const string TOKEN = 'kq3-Zx9_vQm3kT8wLr2Nf6Yb1Hc4Pj7Sd0Ga5Ue8Wq2';
    private const string NEW_TOKEN = 'Zx9vQm3kT8wLr2Nf6Yb1Hc4Pj7Sd0Ga5Ue8Wq2Ki3Mo';

    private int $nextItem = 1;

    #[Test]
    public function the_creator_owns_a_new_list_with_no_members_or_items(): void
    {
        $list = $this->newList();

        self::assertSame(self::LIST, $list->id()->value());
        self::assertTrue($list->ownerId()->equals(self::user(self::MARIA)));
        self::assertSame('Compra semanal', $list->name()->value());
        self::assertSame(self::TOKEN, $list->shareToken()->value());
        self::assertSame([], $list->members());
        self::assertSame([], $list->items());
        self::assertEquals(self::at('10:00'), $list->createdAt());
        self::assertEquals(self::at('10:00'), $list->updatedAt());
        self::assertSame(1, $list->version());
    }

    #[Test]
    public function a_new_list_has_not_been_saved_yet(): void
    {
        $list = $this->newList();

        self::assertSame(0, $list->loadedVersion());
        self::assertTrue($list->hasUnsavedChanges());
    }

    #[Test]
    public function a_stored_list_has_no_unsaved_changes_until_it_changes(): void
    {
        $list = $this->storedList();
        self::assertSame(1, $list->loadedVersion());
        self::assertFalse($list->hasUnsavedChanges());

        $list->rename(self::user(self::MARIA), ListName::fromString('Otra'), self::at('11:00'));

        self::assertSame(1, $list->loadedVersion());
        self::assertSame(2, $list->version());
        self::assertTrue($list->hasUnsavedChanges());
    }

    #[Test]
    public function only_the_owner_and_members_can_access_the_list(): void
    {
        $list = $this->storedList();
        $list->join(self::user(self::JOAN), self::at('11:00'));

        self::assertTrue($list->canBeAccessedBy(self::user(self::MARIA)));
        self::assertTrue($list->canBeAccessedBy(self::user(self::JOAN)));
        self::assertFalse($list->canBeAccessedBy(self::user(self::PEDRO)));
        self::assertTrue($list->isOwnedBy(self::user(self::MARIA)));
        self::assertFalse($list->isOwnedBy(self::user(self::JOAN)));
    }

    #[Test]
    public function joining_makes_the_user_a_member_and_changes_the_version(): void
    {
        $list = $this->storedList();

        $list->join(self::user(self::JOAN), self::at('11:00'));

        self::assertEquals([new Member(self::user(self::JOAN), self::at('11:00'))], $list->members());
        self::assertSame(2, $list->version());
        self::assertEquals(self::at('11:00'), $list->updatedAt());
    }

    #[Test]
    public function joining_again_or_joining_your_own_list_changes_nothing(): void
    {
        $list = $this->storedList();
        $list->join(self::user(self::JOAN), self::at('11:00'));

        $list->join(self::user(self::JOAN), self::at('12:00'));
        $list->join(self::user(self::MARIA), self::at('12:00'));

        self::assertEquals([new Member(self::user(self::JOAN), self::at('11:00'))], $list->members());
        self::assertSame(2, $list->version());
    }

    #[Test]
    public function the_owner_renames_the_list(): void
    {
        $list = $this->storedList();

        $list->rename(self::user(self::MARIA), ListName::fromString('Fiesta'), self::at('11:00'));

        self::assertSame('Fiesta', $list->name()->value());
        self::assertSame(2, $list->version());
        self::assertEquals(self::at('11:00'), $list->updatedAt());
    }

    #[Test]
    public function a_member_cannot_rename_the_list_and_is_told_it_does_not_exist(): void
    {
        $list = $this->listWithJoan();

        $this->expectException(ShoppingListNotFound::class);

        $list->rename(self::user(self::JOAN), ListName::fromString('Fiesta'), self::at('11:00'));
    }

    #[Test]
    public function regenerating_the_link_replaces_the_token_and_keeps_the_members(): void
    {
        $list = $this->listWithJoan();

        $list->regenerateShareToken(self::user(self::MARIA), ShareToken::fromString(self::NEW_TOKEN), self::at('12:00'));

        self::assertSame(self::NEW_TOKEN, $list->shareToken()->value());
        self::assertCount(1, $list->members());
        self::assertSame(3, $list->version());
    }

    #[Test]
    public function a_member_cannot_regenerate_the_link(): void
    {
        $list = $this->listWithJoan();

        $this->expectException(ShoppingListNotFound::class);

        $list->regenerateShareToken(self::user(self::JOAN), ShareToken::fromString(self::NEW_TOKEN), self::at('12:00'));
    }

    #[Test]
    public function the_owner_removes_a_member(): void
    {
        $list = $this->listWithJoan();

        $list->removeMember(self::user(self::MARIA), self::user(self::JOAN), self::at('12:00'));

        self::assertSame([], $list->members());
        self::assertFalse($list->canBeAccessedBy(self::user(self::JOAN)));
        self::assertSame(3, $list->version());
    }

    #[Test]
    public function removing_someone_who_is_not_a_member_is_not_found(): void
    {
        $list = $this->listWithJoan();

        $this->expectException(MemberNotFound::class);

        $list->removeMember(self::user(self::MARIA), self::user(self::PEDRO), self::at('12:00'));
    }

    #[Test]
    public function a_member_cannot_remove_members(): void
    {
        $list = $this->listWithJoan();

        $this->expectException(ShoppingListNotFound::class);

        $list->removeMember(self::user(self::JOAN), self::user(self::JOAN), self::at('12:00'));
    }

    #[Test]
    public function a_removed_member_can_join_again_with_the_link(): void
    {
        $list = $this->listWithJoan();
        $list->removeMember(self::user(self::MARIA), self::user(self::JOAN), self::at('12:00'));

        $list->join(self::user(self::JOAN), self::at('13:00'));

        self::assertTrue($list->canBeAccessedBy(self::user(self::JOAN)));
    }

    #[Test]
    public function a_member_leaves_the_list(): void
    {
        $list = $this->listWithJoan();

        $list->leave(self::user(self::JOAN), self::at('12:00'));

        self::assertSame([], $list->members());
        self::assertSame(3, $list->version());
    }

    #[Test]
    public function the_owner_cannot_leave_their_own_list(): void
    {
        $list = $this->listWithJoan();

        $this->expectException(OwnerCannotLeave::class);

        $list->leave(self::user(self::MARIA), self::at('12:00'));
    }

    #[Test]
    public function an_outsider_cannot_leave_a_list_they_cannot_see(): void
    {
        $list = $this->listWithJoan();

        $this->expectException(ShoppingListNotFound::class);

        $list->leave(self::user(self::PEDRO), self::at('12:00'));
    }

    #[Test]
    public function only_the_owner_can_delete_the_list(): void
    {
        $list = $this->listWithJoan();
        $list->delete(self::user(self::MARIA));

        $this->expectException(ShoppingListNotFound::class);

        $list->delete(self::user(self::JOAN));
    }

    #[Test]
    public function an_outsider_cannot_delete_the_list(): void
    {
        $this->expectException(ShoppingListNotFound::class);

        $this->storedList()->delete(self::user(self::PEDRO));
    }

    #[Test]
    public function owner_and_members_add_items_at_the_end(): void
    {
        $list = $this->listWithJoan();

        $bread = $this->addText($list, self::MARIA, 'pan');
        $milk = $list->addProductItem(self::user(self::JOAN), $this->nextItemId(), self::product(self::MILK), Quantity::of(2), self::at('13:00'));

        $items = $list->items();
        self::assertCount(2, $items);
        self::assertTrue($items[0]->id()->equals($bread));
        self::assertEquals(new FreeTextItem(ItemText::fromString('pan')), $items[0]->content());
        self::assertSame(1, $items[0]->quantity()->value());
        self::assertFalse($items[0]->isChecked());
        self::assertTrue($items[1]->id()->equals($milk));
        self::assertEquals(new ProductItem(self::product(self::MILK)), $items[1]->content());
        self::assertSame(2, $items[1]->quantity()->value());
        self::assertSame(4, $list->version());
        self::assertEquals(self::at('13:00'), $list->updatedAt());
    }

    #[Test]
    public function an_outsider_cannot_add_items(): void
    {
        $list = $this->storedList();

        $this->expectException(ShoppingListNotFound::class);

        $this->addText($list, self::PEDRO, 'pan');
    }

    #[Test]
    public function adding_a_product_already_in_the_list_and_unchecked_adds_to_its_quantity(): void
    {
        $list = $this->storedList();
        $milk = $this->addProduct($list, self::MILK, 2);
        $this->addText($list, self::MARIA, 'pan');

        $merged = $this->addProduct($list, self::MILK, 3);

        self::assertTrue($merged->equals($milk));
        self::assertCount(2, $list->items());
        self::assertSame(5, $list->items()[0]->quantity()->value());
    }

    #[Test]
    public function merging_a_product_above_99_is_rejected(): void
    {
        $list = $this->storedList();
        $this->addProduct($list, self::MILK, 98);

        $this->expectException(InvalidQuantity::class);

        $this->addProduct($list, self::MILK, 2);
    }

    #[Test]
    public function adding_a_product_whose_item_is_checked_creates_a_new_item(): void
    {
        $list = $this->storedList();
        $milk = $this->addProduct($list, self::MILK, 2);
        $list->check(self::user(self::MARIA), $milk, self::at('13:00'));

        $second = $this->addProduct($list, self::MILK, 1);

        self::assertFalse($second->equals($milk));
        self::assertCount(2, $list->items());
    }

    #[Test]
    public function changing_the_quantity_of_an_item(): void
    {
        $list = $this->storedList();
        $bread = $this->addText($list, self::MARIA, 'pan');

        $list->changeQuantity(self::user(self::MARIA), $bread, Quantity::of(4), self::at('14:00'));

        self::assertSame(4, $list->items()[0]->quantity()->value());
        self::assertSame(3, $list->version());
        self::assertEquals(self::at('14:00'), $list->updatedAt());
    }

    #[Test]
    public function checking_sets_the_item_checked_and_checking_again_changes_nothing(): void
    {
        $list = $this->storedList();
        $bread = $this->addText($list, self::MARIA, 'pan');

        $list->check(self::user(self::MARIA), $bread, self::at('14:00'));
        $list->check(self::user(self::MARIA), $bread, self::at('15:00'));

        self::assertTrue($list->items()[0]->isChecked());
        self::assertSame(3, $list->version());
        self::assertEquals(self::at('14:00'), $list->updatedAt());
    }

    #[Test]
    public function unchecking_sets_the_item_unchecked(): void
    {
        $list = $this->storedList();
        $bread = $this->addText($list, self::MARIA, 'pan');
        $list->check(self::user(self::MARIA), $bread, self::at('14:00'));

        $list->uncheck(self::user(self::MARIA), $bread, self::at('15:00'));
        $list->uncheck(self::user(self::MARIA), $bread, self::at('16:00'));

        self::assertFalse($list->items()[0]->isChecked());
        self::assertSame(4, $list->version());
    }

    #[Test]
    public function changing_an_unknown_item_is_not_found(): void
    {
        $list = $this->storedList();

        $this->expectException(ListItemNotFound::class);

        $list->check(self::user(self::MARIA), ListItemId::fromString(self::MILK), self::at('14:00'));
    }

    #[Test]
    public function removing_an_item(): void
    {
        $list = $this->storedList();
        $bread = $this->addText($list, self::MARIA, 'pan');
        $eggs = $this->addText($list, self::MARIA, 'huevos');

        $list->removeItem(self::user(self::MARIA), $bread, self::at('14:00'));

        self::assertCount(1, $list->items());
        self::assertTrue($list->items()[0]->id()->equals($eggs));
        self::assertSame(4, $list->version());
    }

    #[Test]
    public function removing_an_unknown_item_is_not_found(): void
    {
        $this->expectException(ListItemNotFound::class);

        $this->storedList()->removeItem(self::user(self::MARIA), ListItemId::fromString(self::MILK), self::at('14:00'));
    }

    #[Test]
    public function moving_an_item_to_a_zero_based_position(): void
    {
        $list = $this->storedList();
        $a = $this->addText($list, self::MARIA, 'a');
        $this->addText($list, self::MARIA, 'b');
        $c = $this->addText($list, self::MARIA, 'c');

        $list->moveItem(self::user(self::MARIA), $c, 0, self::at('14:00'));
        self::assertSame(['c', 'a', 'b'], self::texts($list));

        $list->moveItem(self::user(self::MARIA), $c, 1, self::at('14:00'));
        self::assertSame(['a', 'c', 'b'], self::texts($list));

        $list->moveItem(self::user(self::MARIA), $a, 2, self::at('14:00'));
        self::assertSame(['c', 'b', 'a'], self::texts($list));
        self::assertSame(7, $list->version());
    }

    #[Test]
    public function moving_an_item_past_the_end_puts_it_last(): void
    {
        $list = $this->storedList();
        $a = $this->addText($list, self::MARIA, 'a');
        $this->addText($list, self::MARIA, 'b');

        $list->moveItem(self::user(self::MARIA), $a, 7, self::at('14:00'));

        self::assertSame(['b', 'a'], self::texts($list));
    }

    #[Test]
    public function moving_an_item_to_where_it_already_is_changes_nothing(): void
    {
        $list = $this->storedList();
        $a = $this->addText($list, self::MARIA, 'a');

        $list->moveItem(self::user(self::MARIA), $a, 0, self::at('14:00'));

        self::assertSame(2, $list->version());
    }

    #[Test]
    public function a_negative_position_is_rejected(): void
    {
        $list = $this->storedList();
        $a = $this->addText($list, self::MARIA, 'a');

        $this->expectException(InvalidItemPosition::class);

        $list->moveItem(self::user(self::MARIA), $a, -1, self::at('14:00'));
    }

    #[Test]
    public function clearing_checked_items_keeps_the_unchecked_ones_in_order(): void
    {
        $list = $this->storedList();
        $a = $this->addText($list, self::MARIA, 'a');
        $this->addText($list, self::MARIA, 'b');
        $c = $this->addText($list, self::MARIA, 'c');
        $this->addText($list, self::MARIA, 'd');
        $list->check(self::user(self::MARIA), $a, self::at('14:00'));
        $list->check(self::user(self::MARIA), $c, self::at('14:00'));

        $list->clearChecked(self::user(self::MARIA), self::at('15:00'));

        self::assertSame(['b', 'd'], self::texts($list));
        self::assertSame(8, $list->version());
    }

    #[Test]
    public function clearing_when_nothing_is_checked_changes_nothing(): void
    {
        $list = $this->storedList();
        $this->addText($list, self::MARIA, 'a');

        $list->clearChecked(self::user(self::MARIA), self::at('15:00'));

        self::assertSame(2, $list->version());
    }

    #[Test]
    public function a_reconstituted_list_keeps_its_state(): void
    {
        $item = ListItem::reconstitute(ListItemId::fromString(self::MILK), new ProductItem(self::product(self::BREAD)), Quantity::of(3), true);
        $list = ShoppingList::reconstitute(
            ShoppingListId::fromString(self::LIST),
            self::user(self::MARIA),
            ListName::fromString('Compra'),
            ShareToken::fromString(self::TOKEN),
            [new Member(self::user(self::JOAN), self::at('11:00'))],
            [$item],
            self::at('10:00'),
            self::at('12:00'),
            7,
        );

        self::assertSame(7, $list->version());
        self::assertSame(7, $list->loadedVersion());
        self::assertEquals([$item], $list->items());
        self::assertEquals(self::at('12:00'), $list->updatedAt());
    }

    #[Test]
    public function a_copy_does_not_share_items_with_the_original(): void
    {
        $list = $this->storedList();
        $bread = $this->addText($list, self::MARIA, 'pan');

        $copy = clone $list;
        $copy->check(self::user(self::MARIA), $bread, self::at('14:00'));

        self::assertFalse($list->items()[0]->isChecked());
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

    private function storedList(): ShoppingList
    {
        return ShoppingList::reconstitute(
            ShoppingListId::fromString(self::LIST),
            self::user(self::MARIA),
            ListName::fromString('Compra semanal'),
            ShareToken::fromString(self::TOKEN),
            [],
            [],
            self::at('10:00'),
            self::at('10:00'),
            1,
        );
    }

    private function listWithJoan(): ShoppingList
    {
        $list = $this->storedList();
        $list->join(self::user(self::JOAN), self::at('11:00'));

        return $list;
    }

    private function addText(ShoppingList $list, string $actor, string $text): ListItemId
    {
        $id = $this->nextItemId();
        $list->addFreeTextItem(self::user($actor), $id, ItemText::fromString($text), Quantity::default(), self::at('13:00'));

        return $id;
    }

    private function addProduct(ShoppingList $list, string $product, int $quantity): ListItemId
    {
        return $list->addProductItem(self::user(self::MARIA), $this->nextItemId(), self::product($product), Quantity::of($quantity), self::at('13:00'));
    }

    private function nextItemId(): ListItemId
    {
        return ListItemId::fromString(\sprintf('0193cccc-0000-7000-8000-%012d', $this->nextItem++));
    }

    /**
     * @return list<string>
     */
    private static function texts(ShoppingList $list): array
    {
        return array_map(
            static fn (ListItem $item): string => $item->content() instanceof FreeTextItem ? $item->content()->text->value() : '?',
            $list->items(),
        );
    }

    private static function user(string $id): UserId
    {
        return UserId::fromString($id);
    }

    private static function product(string $id): ProductId
    {
        return ProductId::fromString($id);
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-11 '.$time, new \DateTimeZone('UTC'));
    }
}
