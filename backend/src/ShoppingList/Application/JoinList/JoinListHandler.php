<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\JoinList;

use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShoppingListRepository;
use App\ShoppingList\Domain\ValueObject\InvalidShareToken;
use App\ShoppingList\Domain\ValueObject\ShareToken;

/**
 * Opening the secret link makes the actor a member (idempotent; the owner joining is a no-op).
 */
final readonly class JoinListHandler
{
    public function __construct(
        private ListUpdater $updater,
        private ShoppingListRepository $lists,
        private Clock $clock,
    ) {
    }

    /**
     * @return string the id of the joined list
     */
    public function __invoke(JoinListCommand $command): string
    {
        try {
            $token = ShareToken::fromString($command->shareToken);
        } catch (InvalidShareToken) {
            throw ShareLinkNotValid::create();
        }
        $actor = UserId::fromString($command->actorId);

        // Looked up by token on every attempt, so a link regenerated meanwhile no longer works.
        return $this->updater->updateLoaded(
            fn (): ShoppingList => $this->lists->findByShareToken($token) ?? throw ShareLinkNotValid::create(),
            function (ShoppingList $list) use ($actor): string {
                $list->join($actor, $this->clock->now());

                return $list->id()->value();
            },
        );
    }
}
