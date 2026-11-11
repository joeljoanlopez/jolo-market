<?php

declare(strict_types=1);

namespace App\Tests\Unit\ShoppingList\Application;

use App\ShoppingList\Application\JoinList\ShareLinkNotValid;
use App\ShoppingList\Application\ManageList\DeleteListCommand;
use App\ShoppingList\Application\ManageList\DeleteListHandler;
use App\ShoppingList\Application\ManageList\LeaveListCommand;
use App\ShoppingList\Application\ManageList\LeaveListHandler;
use App\ShoppingList\Application\ManageList\RegenerateShareTokenCommand;
use App\ShoppingList\Application\ManageList\RegenerateShareTokenHandler;
use App\ShoppingList\Application\ManageList\RemoveMemberCommand;
use App\ShoppingList\Application\ManageList\RemoveMemberHandler;
use App\ShoppingList\Application\ManageList\RenameListCommand;
use App\ShoppingList\Application\ManageList\RenameListHandler;
use App\ShoppingList\Domain\Model\MemberNotFound;
use App\ShoppingList\Domain\Model\OwnerCannotLeave;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;
use App\ShoppingList\Domain\ValueObject\InvalidListName;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;
use App\Tests\Support\ShoppingList\SequentialShareTokenGenerator;
use PHPUnit\Framework\Attributes\Test;

final class ListMembershipHandlersTest extends ShoppingListHandlersTestCase
{
    #[Test]
    public function creating_a_list_makes_the_actor_its_owner(): void
    {
        $listId = $this->createList(self::MARIA, '  Compra semanal ');

        self::assertSame('00000000-0000-4000-8000-000000000001', $listId);
        $list = $this->list($listId);
        self::assertTrue($list->isOwnedBy(self::user(self::MARIA)));
        self::assertSame('Compra semanal', $list->name()->value());
        self::assertSame(SequentialShareTokenGenerator::nth(1), $list->shareToken()->value());
        self::assertEquals(self::at('2026-11-11 10:00:00'), $list->createdAt());
    }

    #[Test]
    public function a_list_needs_a_valid_name(): void
    {
        $this->expectException(InvalidListName::class);

        $this->createList(self::MARIA, ' ');
    }

    #[Test]
    public function opening_the_link_makes_the_user_a_member_once(): void
    {
        $listId = $this->createList();
        $token = $this->list($listId)->shareToken()->value();

        self::assertSame($listId, $this->join(self::JOAN, $token));
        self::assertSame($listId, $this->join(self::JOAN, $token));
        self::assertSame($listId, $this->join(self::MARIA, $token));

        $list = $this->list($listId);
        self::assertCount(1, $list->members());
        self::assertTrue($list->canBeAccessedBy(self::user(self::JOAN)));
        self::assertSame(2, $list->version());
    }

    #[Test]
    public function an_unknown_link_is_not_valid(): void
    {
        $this->createList();

        $this->expectException(ShareLinkNotValid::class);

        $this->join(self::JOAN, SequentialShareTokenGenerator::nth(99));
    }

    #[Test]
    public function a_malformed_link_is_not_valid(): void
    {
        $this->expectException(ShareLinkNotValid::class);

        $this->join(self::JOAN, 'short');
    }

    #[Test]
    public function the_owner_renames_the_list(): void
    {
        $listId = $this->createList();

        ($this->rename())(new RenameListCommand(self::MARIA, $listId, 'Fiesta'));

        self::assertSame('Fiesta', $this->list($listId)->name()->value());
    }

    #[Test]
    public function a_member_renaming_the_list_is_told_it_does_not_exist(): void
    {
        $listId = $this->sharedList();

        $this->expectException(ShoppingListNotFound::class);

        ($this->rename())(new RenameListCommand(self::JOAN, $listId, 'Fiesta'));
    }

    #[Test]
    public function an_unknown_or_malformed_list_is_not_found(): void
    {
        foreach ([self::UNKNOWN_LIST, 'not-a-uuid'] as $listId) {
            try {
                ($this->rename())(new RenameListCommand(self::MARIA, $listId, 'Fiesta'));
                self::fail('Expected a ShoppingListNotFound.');
            } catch (ShoppingListNotFound $notFound) {
                self::assertSame(\sprintf('Shopping list "%s" was not found.', $listId), $notFound->getMessage());
            }
        }
    }

    #[Test]
    public function regenerating_the_link_invalidates_the_old_one_and_keeps_the_members(): void
    {
        $listId = $this->sharedList();
        $oldToken = $this->list($listId)->shareToken()->value();

        $newToken = ($this->regenerate())(new RegenerateShareTokenCommand(self::MARIA, $listId));

        self::assertSame(SequentialShareTokenGenerator::nth(2), $newToken);
        self::assertSame($newToken, $this->list($listId)->shareToken()->value());
        self::assertTrue($this->list($listId)->canBeAccessedBy(self::user(self::JOAN)));
        self::assertSame($listId, $this->join(self::PEDRO, $newToken));
        $this->expectException(ShareLinkNotValid::class);
        $this->join(self::PEDRO, $oldToken);
    }

    #[Test]
    public function a_member_cannot_regenerate_the_link(): void
    {
        $listId = $this->sharedList();

        $this->expectException(ShoppingListNotFound::class);

        ($this->regenerate())(new RegenerateShareTokenCommand(self::JOAN, $listId));
    }

    #[Test]
    public function the_owner_removes_a_member_who_can_join_again_with_the_link(): void
    {
        $listId = $this->sharedList();

        ($this->removeMember())(new RemoveMemberCommand(self::MARIA, $listId, self::JOAN));

        self::assertFalse($this->list($listId)->canBeAccessedBy(self::user(self::JOAN)));
        $this->join(self::JOAN, $this->list($listId)->shareToken()->value());
        self::assertTrue($this->list($listId)->canBeAccessedBy(self::user(self::JOAN)));
    }

    #[Test]
    public function removing_a_user_who_is_not_a_member_is_not_found(): void
    {
        $listId = $this->sharedList();

        foreach ([self::PEDRO, 'not-a-uuid'] as $userId) {
            try {
                ($this->removeMember())(new RemoveMemberCommand(self::MARIA, $listId, $userId));
                self::fail('Expected a MemberNotFound.');
            } catch (MemberNotFound) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_member_cannot_remove_members(): void
    {
        $listId = $this->sharedList();

        $this->expectException(ShoppingListNotFound::class);

        ($this->removeMember())(new RemoveMemberCommand(self::JOAN, $listId, self::JOAN));
    }

    #[Test]
    public function a_member_leaves_the_list(): void
    {
        $listId = $this->sharedList();

        ($this->leave())(new LeaveListCommand(self::JOAN, $listId));

        self::assertFalse($this->list($listId)->canBeAccessedBy(self::user(self::JOAN)));
    }

    #[Test]
    public function the_owner_cannot_leave(): void
    {
        $listId = $this->sharedList();

        $this->expectException(OwnerCannotLeave::class);

        ($this->leave())(new LeaveListCommand(self::MARIA, $listId));
    }

    #[Test]
    public function an_outsider_cannot_leave(): void
    {
        $listId = $this->sharedList();

        $this->expectException(ShoppingListNotFound::class);

        ($this->leave())(new LeaveListCommand(self::PEDRO, $listId));
    }

    #[Test]
    public function the_owner_deletes_the_list_for_everyone(): void
    {
        $listId = $this->sharedList();

        ($this->delete())(new DeleteListCommand(self::MARIA, $listId));

        self::assertNull($this->lists->findById(ShoppingListId::fromString($listId)));
        self::assertSame([], $this->lists->findByUser(self::user(self::JOAN)));
    }

    #[Test]
    public function a_member_cannot_delete_the_list(): void
    {
        $listId = $this->sharedList();

        try {
            ($this->delete())(new DeleteListCommand(self::JOAN, $listId));
            self::fail('Expected a ShoppingListNotFound.');
        } catch (ShoppingListNotFound) {
            self::assertNotNull($this->lists->findById(ShoppingListId::fromString($listId)));
        }
    }

    private function rename(): RenameListHandler
    {
        return new RenameListHandler($this->updater, $this->clock);
    }

    private function regenerate(): RegenerateShareTokenHandler
    {
        return new RegenerateShareTokenHandler($this->updater, $this->tokens, $this->clock);
    }

    private function removeMember(): RemoveMemberHandler
    {
        return new RemoveMemberHandler($this->updater, $this->clock);
    }

    private function leave(): LeaveListHandler
    {
        return new LeaveListHandler($this->updater, $this->clock);
    }

    private function delete(): DeleteListHandler
    {
        return new DeleteListHandler($this->updater, $this->lists);
    }
}
