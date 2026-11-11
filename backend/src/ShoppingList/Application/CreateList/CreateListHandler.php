<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\CreateList;

use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShareTokenGenerator;
use App\ShoppingList\Domain\Port\ShoppingListRepository;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;

final readonly class CreateListHandler
{
    public function __construct(
        private ShoppingListRepository $lists,
        private ShareTokenGenerator $shareTokens,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    /**
     * @return string the id of the new list, owned by the actor
     */
    public function __invoke(CreateListCommand $command): string
    {
        $list = ShoppingList::create(
            ShoppingListId::fromString($this->ids->generate()->value()),
            UserId::fromString($command->actorId),
            ListName::fromString($command->name),
            $this->shareTokens->generate(),
            $this->clock->now(),
        );
        $this->lists->save($list);

        return $list->id()->value();
    }
}
