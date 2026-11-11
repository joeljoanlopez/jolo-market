<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Application\ListUpdater;
use App\ShoppingList\Application\ProductCatalog;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ProductId;
use App\ShoppingList\Domain\ValueObject\Quantity;

/**
 * Adds a free-text or product item at the end of the list (PD-45). A product already in the
 * list and unchecked gets the quantity added instead.
 */
final readonly class AddItemHandler
{
    public function __construct(
        private ListUpdater $updater,
        private ProductCatalog $catalog,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    /**
     * @return string the id of the new item, or of the item the quantity was added to
     */
    public function __invoke(AddItemCommand $command): string
    {
        $actor = UserId::fromString($command->actorId);
        $quantity = null === $command->quantity ? Quantity::default() : Quantity::of($command->quantity);
        // Generated once, so a retry adds the same item (PD-43).
        $itemId = ListItemId::fromString($this->ids->generate()->value());

        if (AddItemCommand::TEXT === $command->type) {
            $text = ItemText::fromString($command->text ?? '');

            return $this->updater->update($command->listId, function (ShoppingList $list) use ($actor, $itemId, $text, $quantity): string {
                $list->addFreeTextItem($actor, $itemId, $text, $quantity, $this->clock->now());

                return $itemId->value();
            });
        }

        if (AddItemCommand::PRODUCT === $command->type) {
            $productId = $this->availableProduct($command->productId);

            return $this->updater->update($command->listId, fn (ShoppingList $list): string => $list->addProductItem($actor, $itemId, $productId, $quantity, $this->clock->now())->value());
        }

        throw InvalidItemType::create();
    }

    private function availableProduct(?string $productId): ProductId
    {
        try {
            $id = ProductId::fromString($productId ?? '');
        } catch (InvalidUuid) {
            throw ProductNotAvailable::withId($productId);
        }

        $product = $this->catalog->findByIds([$id])[$id->value()] ?? null;
        if (null === $product || !$product->available) {
            throw ProductNotAvailable::withId($productId);
        }

        return $id;
    }
}
