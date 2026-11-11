<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;

final readonly class ClearCheckedItemsHandler
{
    public function __construct(
        private ListUpdater $updater,
        private Clock $clock,
    ) {
    }

    public function __invoke(ClearCheckedItemsCommand $command): void
    {
        $actor = UserId::fromString($command->actorId);

        $this->updater->update($command->listId, function (ShoppingList $list) use ($actor): void {
            $list->clearChecked($actor, $this->clock->now());
        });
    }
}
