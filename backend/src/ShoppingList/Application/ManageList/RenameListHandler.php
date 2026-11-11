<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\ManageList;

use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\ValueObject\ListName;

final readonly class RenameListHandler
{
    public function __construct(
        private ListUpdater $updater,
        private Clock $clock,
    ) {
    }

    public function __invoke(RenameListCommand $command): void
    {
        $actor = UserId::fromString($command->actorId);
        $name = ListName::fromString($command->name);

        $this->updater->update($command->listId, function (ShoppingList $list) use ($actor, $name): void {
            $list->rename($actor, $name, $this->clock->now());
        });
    }
}
