<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\ManageList;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Port\ShoppingListRepository;

/**
 * PD-44: the owner deletes the list for everyone; members then get a 404.
 */
final readonly class DeleteListHandler
{
    public function __construct(
        private ListUpdater $updater,
        private ShoppingListRepository $lists,
    ) {
    }

    public function __invoke(DeleteListCommand $command): void
    {
        $list = $this->updater->load($command->listId);
        $list->delete(UserId::fromString($command->actorId));

        $this->lists->remove($list);
    }
}
