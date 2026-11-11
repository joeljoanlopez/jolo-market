<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Domain\ValueObject\ItemText;
use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ListName;
use App\ShoppingList\Domain\ValueObject\ProductId;
use App\ShoppingList\Domain\ValueObject\Quantity;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;

/**
 * A list with an owner and the members who joined through its secret link (PD-2).
 *
 * Owner and members read and edit items; only the owner renames the list, removes members,
 * regenerates the link and deletes the list (PD-44). Anyone else is told the list does not
 * exist, and so is a member trying an owner-only action (PD-15).
 *
 * Every real change moves the version (the ETag of the list). The version it was loaded with
 * stays apart, so saving can check nobody saved in between (PD-43).
 */
final class ShoppingList
{
    /**
     * @param list<Member>   $members
     * @param list<ListItem> $items
     */
    private function __construct(
        private readonly ShoppingListId $id,
        private readonly UserId $ownerId,
        private ListName $name,
        private ShareToken $shareToken,
        private array $members,
        private array $items,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private int $version,
        private readonly int $loadedVersion,
    ) {
    }

    public static function create(ShoppingListId $id, UserId $ownerId, ListName $name, ShareToken $shareToken, \DateTimeImmutable $now): self
    {
        return new self($id, $ownerId, $name, $shareToken, [], [], $now, $now, 1, 0);
    }

    /**
     * Rebuilds a stored list. Only persistence adapters call it.
     *
     * @param list<Member>   $members in the order they joined
     * @param list<ListItem> $items   in position order
     */
    public static function reconstitute(
        ShoppingListId $id,
        UserId $ownerId,
        ListName $name,
        ShareToken $shareToken,
        array $members,
        array $items,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        int $version,
    ): self {
        return new self($id, $ownerId, $name, $shareToken, $members, $items, $createdAt, $updatedAt, $version, $version);
    }

    public function __clone()
    {
        $this->items = array_map(static fn (ListItem $item): ListItem => clone $item, $this->items);
    }

    public function id(): ShoppingListId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function name(): ListName
    {
        return $this->name;
    }

    public function shareToken(): ShareToken
    {
        return $this->shareToken;
    }

    /**
     * @return list<Member>
     */
    public function members(): array
    {
        return $this->members;
    }

    /**
     * @return list<ListItem> in position order
     */
    public function items(): array
    {
        return $this->items;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * The version it was loaded with; 0 for a list that was never saved.
     */
    public function loadedVersion(): int
    {
        return $this->loadedVersion;
    }

    public function hasUnsavedChanges(): bool
    {
        return $this->version !== $this->loadedVersion;
    }

    public function isOwnedBy(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function canBeAccessedBy(UserId $userId): bool
    {
        return $this->isOwnedBy($userId) || null !== $this->memberIndexOf($userId);
    }

    /**
     * Idempotent; the owner joining their own list is a no-op. A removed member may join again
     * while the link is valid (PD-45).
     */
    public function join(UserId $userId, \DateTimeImmutable $now): void
    {
        if ($this->canBeAccessedBy($userId)) {
            return;
        }

        $this->members[] = new Member($userId, $now);
        $this->changed($now);
    }

    public function rename(UserId $actor, ListName $name, \DateTimeImmutable $now): void
    {
        $this->assertOwner($actor);

        $this->name = $name;
        $this->changed($now);
    }

    /**
     * The old link stops working; current members stay.
     */
    public function regenerateShareToken(UserId $actor, ShareToken $shareToken, \DateTimeImmutable $now): void
    {
        $this->assertOwner($actor);

        $this->shareToken = $shareToken;
        $this->changed($now);
    }

    public function removeMember(UserId $actor, UserId $member, \DateTimeImmutable $now): void
    {
        $this->assertOwner($actor);

        $this->dropMember($member);
        $this->changed($now);
    }

    /**
     * PD-44: a member leaves on their own; the owner can only delete the list.
     */
    public function leave(UserId $actor, \DateTimeImmutable $now): void
    {
        $this->assertCanAccess($actor);
        if ($this->isOwnedBy($actor)) {
            throw OwnerCannotLeave::create();
        }

        $this->dropMember($actor);
        $this->changed($now);
    }

    /**
     * PD-44: only the owner deletes the list. The repository then removes it for everyone.
     */
    public function delete(UserId $actor): void
    {
        $this->assertOwner($actor);
    }

    public function addFreeTextItem(UserId $actor, ListItemId $itemId, ItemText $text, Quantity $quantity, \DateTimeImmutable $now): void
    {
        $this->assertCanAccess($actor);

        $this->items[] = ListItem::add($itemId, new FreeTextItem($text), $quantity);
        $this->changed($now);
    }

    /**
     * PD-45: a product that is already in the list and unchecked gets the quantity added
     * instead of a second item.
     *
     * @return ListItemId the new item, or the item the quantity was added to
     */
    public function addProductItem(UserId $actor, ListItemId $itemId, ProductId $productId, Quantity $quantity, \DateTimeImmutable $now): ListItemId
    {
        $this->assertCanAccess($actor);

        foreach ($this->items as $item) {
            if ($item->holdsProduct($productId) && !$item->isChecked()) {
                $item->changeQuantity($item->quantity()->add($quantity));
                $this->changed($now);

                return $item->id();
            }
        }

        $this->items[] = ListItem::add($itemId, new ProductItem($productId), $quantity);
        $this->changed($now);

        return $itemId;
    }

    public function changeQuantity(UserId $actor, ListItemId $itemId, Quantity $quantity, \DateTimeImmutable $now): void
    {
        $this->assertCanAccess($actor);

        if ($this->item($itemId)->changeQuantity($quantity)) {
            $this->changed($now);
        }
    }

    public function check(UserId $actor, ListItemId $itemId, \DateTimeImmutable $now): void
    {
        $this->setChecked($actor, $itemId, true, $now);
    }

    public function uncheck(UserId $actor, ListItemId $itemId, \DateTimeImmutable $now): void
    {
        $this->setChecked($actor, $itemId, false, $now);
    }

    /**
     * Moves the item to a 0-based position; past the end means last (PD-45).
     */
    public function moveItem(UserId $actor, ListItemId $itemId, int $position, \DateTimeImmutable $now): void
    {
        $this->assertCanAccess($actor);
        if ($position < 0) {
            throw InvalidItemPosition::negative();
        }

        $from = $this->indexOf($itemId);
        $to = min($position, \count($this->items) - 1);
        if ($from === $to) {
            return;
        }

        $item = $this->items[$from];
        $items = $this->items;
        array_splice($items, $from, 1);
        array_splice($items, $to, 0, [$item]);
        $this->items = $items;
        $this->changed($now);
    }

    public function removeItem(UserId $actor, ListItemId $itemId, \DateTimeImmutable $now): void
    {
        $this->assertCanAccess($actor);

        $items = $this->items;
        unset($items[$this->indexOf($itemId)]);
        $this->items = array_values($items);
        $this->changed($now);
    }

    public function clearChecked(UserId $actor, \DateTimeImmutable $now): void
    {
        $this->assertCanAccess($actor);

        $unchecked = array_values(array_filter($this->items, static fn (ListItem $item): bool => !$item->isChecked()));
        if (\count($unchecked) === \count($this->items)) {
            return;
        }

        $this->items = $unchecked;
        $this->changed($now);
    }

    private function setChecked(UserId $actor, ListItemId $itemId, bool $checked, \DateTimeImmutable $now): void
    {
        $this->assertCanAccess($actor);

        if ($this->item($itemId)->setChecked($checked)) {
            $this->changed($now);
        }
    }

    private function assertCanAccess(UserId $actor): void
    {
        if (!$this->canBeAccessedBy($actor)) {
            throw ShoppingListNotFound::withId($this->id->value());
        }
    }

    private function assertOwner(UserId $actor): void
    {
        if (!$this->isOwnedBy($actor)) {
            throw ShoppingListNotFound::withId($this->id->value());
        }
    }

    private function dropMember(UserId $userId): void
    {
        $index = $this->memberIndexOf($userId) ?? throw MemberNotFound::withId($userId->value());

        $members = $this->members;
        unset($members[$index]);
        $this->members = array_values($members);
    }

    private function memberIndexOf(UserId $userId): ?int
    {
        foreach ($this->members as $index => $member) {
            if ($member->userId->equals($userId)) {
                return $index;
            }
        }

        return null;
    }

    private function item(ListItemId $itemId): ListItem
    {
        return $this->items[$this->indexOf($itemId)];
    }

    private function indexOf(ListItemId $itemId): int
    {
        foreach ($this->items as $index => $item) {
            if ($item->id()->equals($itemId)) {
                return $index;
            }
        }

        throw ListItemNotFound::withId($itemId->value());
    }

    private function changed(\DateTimeImmutable $now): void
    {
        ++$this->version;
        $this->updatedAt = $now;
    }
}
