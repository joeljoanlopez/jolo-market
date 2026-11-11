<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;

final readonly class RemoveItemHandler
{
    public function __construct(
        private ListUpdater $updater,
        private Clock $clock,
    ) {
    }

    public function __invoke(RemoveItemCommand $command): void
    {
        $actor = UserId::fromString($command->actorId);

        $this->updater->update($command->listId, function (ShoppingList $list) use ($actor, $command): void {
            $list->removeItem($actor, ItemReference::in($list, $actor, $command->itemId), $this->clock->now());
        });
    }
}
