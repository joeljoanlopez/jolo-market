<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\ManageList;

use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShareTokenGenerator;

final readonly class RegenerateShareTokenHandler
{
    public function __construct(
        private ListUpdater $updater,
        private ShareTokenGenerator $shareTokens,
        private Clock $clock,
    ) {
    }

    /**
     * @return string the new share token; the old one stops working
     */
    public function __invoke(RegenerateShareTokenCommand $command): string
    {
        $actor = UserId::fromString($command->actorId);
        $token = $this->shareTokens->generate();

        $this->updater->update($command->listId, function (ShoppingList $list) use ($actor, $token): void {
            $list->regenerateShareToken($actor, $token, $this->clock->now());
        });

        return $token->value();
    }
}
