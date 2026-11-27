<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

use App\Shared\Domain\Clock;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\ValueObject\ItemSection;
use App\ShoppingList\Domain\ValueObject\Quantity;

final readonly class UpdateItemHandler
{
    public function __construct(
        private ListUpdater $updater,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateItemCommand $command): void
    {
        $actor = UserId::fromString($command->actorId);
        $quantity = null === $command->quantity ? null : Quantity::of($command->quantity);
        $section = self::section($command);

        $this->updater->update($command->listId, function (ShoppingList $list) use ($actor, $command, $quantity, $section): void {
            $itemId = ItemReference::in($list, $actor, $command->itemId);
            $now = $this->clock->now();

            if (null !== $quantity) {
                $list->changeQuantity($actor, $itemId, $quantity, $now);
            }
            if (true === $command->checked) {
                $list->check($actor, $itemId, $now);
            }
            if (false === $command->checked) {
                $list->uncheck($actor, $itemId, $now);
            }
            if (null !== $command->position) {
                $list->moveItem($actor, $itemId, $command->position, $now);
            }
            if ($command->changeSection) {
                $list->changeItemSection($actor, $itemId, $section, $now);
            }
        });
    }

    private static function section(UpdateItemCommand $command): ?ItemSection
    {
        if (!$command->changeSection || null === $command->section) {
            return null;
        }

        return ItemSection::tryFrom($command->section) ?? throw InvalidItemSection::unknown($command->section);
    }
}
