<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetMyLists;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\GetList\ListView;
use App\ShoppingList\Application\UserDirectory;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShoppingListRepository;

/**
 * "My lists" (E4.11): owned or joined by the actor, most recently updated first.
 */
final readonly class GetMyListsHandler
{
    public function __construct(
        private ShoppingListRepository $lists,
        private UserDirectory $users,
    ) {
    }

    /**
     * @return list<MyListSummary>
     */
    public function __invoke(GetMyListsQuery $query): array
    {
        $actor = UserId::fromString($query->actorId);
        $lists = $this->lists->findByUser($actor);
        if ([] === $lists) {
            return [];
        }

        $owners = [];
        foreach ($lists as $list) {
            $owners[$list->ownerId()->value()] = $list->ownerId();
        }
        $usernames = $this->users->usernamesOf(array_values($owners));

        usort($lists, static fn (ShoppingList $a, ShoppingList $b): int => [$b->updatedAt(), $a->id()->value()] <=> [$a->updatedAt(), $b->id()->value()]);

        return array_map(
            static fn (ShoppingList $list): MyListSummary => new MyListSummary(
                $list->id()->value(),
                $list->name()->value(),
                $list->isOwnedBy($actor) ? ListView::OWNER : ListView::MEMBER,
                $usernames[$list->ownerId()->value()] ?? '',
                \count($list->items()),
                $list->updatedAt(),
            ),
            $lists,
        );
    }
}
