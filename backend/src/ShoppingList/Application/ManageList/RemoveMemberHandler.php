<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\ManageList;

use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\MemberNotFound;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;

final readonly class RemoveMemberHandler
{
    public function __construct(
        private ListUpdater $updater,
        private Clock $clock,
    ) {
    }

    public function __invoke(RemoveMemberCommand $command): void
    {
        $actor = UserId::fromString($command->actorId);

        $this->updater->update($command->listId, function (ShoppingList $list) use ($actor, $command): void {
            try {
                $member = UserId::fromString($command->userId);
            } catch (InvalidUuid) {
                // Only the owner may learn who is not a member; anyone else gets the list's 404.
                throw $list->isOwnedBy($actor) ? MemberNotFound::withId($command->userId) : ShoppingListNotFound::withId($list->id()->value());
            }
            $list->removeMember($actor, $member, $this->clock->now());
        });
    }
}
