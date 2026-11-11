<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\ManageList;

use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;

/**
 * PD-44: a member leaves on their own and can join again later through a valid link.
 */
final readonly class LeaveListHandler
{
    public function __construct(
        private ListUpdater $updater,
        private Clock $clock,
    ) {
    }

    public function __invoke(LeaveListCommand $command): void
    {
        $actor = UserId::fromString($command->actorId);

        $this->updater->update($command->listId, function (ShoppingList $list) use ($actor): void {
            $list->leave($actor, $this->clock->now());
        });
    }
}
