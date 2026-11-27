<?php

declare(strict_types=1);

namespace App\Tests\Unit\ShoppingList\Application;

use App\ShoppingList\Application\CheckListAccess\CheckListAccessHandler;
use App\ShoppingList\Application\CheckListAccess\CheckListAccessQuery;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;
use PHPUnit\Framework\Attributes\Test;

final class CheckListAccessHandlerTest extends ShoppingListHandlersTestCase
{
    #[Test]
    public function the_owner_and_the_members_are_allowed(): void
    {
        $listId = $this->sharedList();

        $this->expectNotToPerformAssertions();
        ($this->handler())(new CheckListAccessQuery(self::MARIA, $listId));
        ($this->handler())(new CheckListAccessQuery(self::JOAN, $listId));
    }

    #[Test]
    public function anyone_else_is_told_the_list_does_not_exist(): void
    {
        $listId = $this->sharedList();

        $this->expectException(ShoppingListNotFound::class);

        ($this->handler())(new CheckListAccessQuery(self::PEDRO, $listId));
    }

    #[Test]
    public function an_unknown_or_malformed_list_does_not_exist(): void
    {
        foreach ([self::UNKNOWN_LIST, 'not-a-uuid'] as $listId) {
            try {
                ($this->handler())(new CheckListAccessQuery(self::MARIA, $listId));
                self::fail('Expected a ShoppingListNotFound.');
            } catch (ShoppingListNotFound) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function handler(): CheckListAccessHandler
    {
        return new CheckListAccessHandler($this->updater);
    }
}
