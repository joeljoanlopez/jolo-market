<?php

declare(strict_types=1);

namespace App\Tests\Support\ShoppingList;

use App\Shared\Domain\ValueObject\UserId;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Port\ShoppingListChangedConcurrently;
use App\ShoppingList\Domain\Port\ShoppingListRepository;
use App\ShoppingList\Domain\ValueObject\ShareToken;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;

/**
 * Keeps copies, like database rows, and checks the loaded version on save like the DBAL
 * adapter. beforeNextSaves() simulates other people saving in between (PD-43).
 */
final class InMemoryShoppingListRepository implements ShoppingListRepository
{
    /** @var array<string, ShoppingList> */
    private array $lists = [];

    /** @var list<callable(self): void> */
    private array $concurrentSaves = [];

    private int $saves = 0;

    private bool $runningConcurrentSave = false;

    /**
     * Each callable runs right before one of the next saves, as another request would.
     *
     * @param callable(self): void ...$concurrentSaves
     */
    public function beforeNextSaves(callable ...$concurrentSaves): void
    {
        $this->concurrentSaves = array_values($concurrentSaves);
    }

    public function save(ShoppingList $list): void
    {
        $concurrentSave = $this->runningConcurrentSave ? null : array_shift($this->concurrentSaves);
        if (null !== $concurrentSave) {
            $this->runningConcurrentSave = true;
            try {
                $concurrentSave($this);
            } finally {
                $this->runningConcurrentSave = false;
            }
        }

        $stored = $this->lists[$list->id()->value()] ?? null;
        $storedVersion = $stored?->version() ?? 0;
        if ($storedVersion !== $list->loadedVersion()) {
            throw ShoppingListChangedConcurrently::withId($list->id()->value());
        }

        ++$this->saves;
        $this->lists[$list->id()->value()] = self::asStored($list);
    }

    public function remove(ShoppingList $list): void
    {
        unset($this->lists[$list->id()->value()]);
    }

    public function findById(ShoppingListId $id): ?ShoppingList
    {
        $list = $this->lists[$id->value()] ?? null;

        return null === $list ? null : clone $list;
    }

    public function findByShareToken(ShareToken $shareToken): ?ShoppingList
    {
        foreach ($this->lists as $list) {
            if ($list->shareToken()->equals($shareToken)) {
                return clone $list;
            }
        }

        return null;
    }

    public function findByUser(UserId $userId): array
    {
        $found = [];
        foreach ($this->lists as $list) {
            if ($list->canBeAccessedBy($userId)) {
                $found[] = clone $list;
            }
        }

        return $found;
    }

    public function saves(): int
    {
        return $this->saves;
    }

    /**
     * A saved list loads with its new version as the loaded one, as it would from the database.
     */
    private static function asStored(ShoppingList $list): ShoppingList
    {
        $copy = clone $list;

        return ShoppingList::reconstitute(
            $copy->id(),
            $copy->ownerId(),
            $copy->name(),
            $copy->shareToken(),
            $copy->members(),
            $copy->items(),
            $copy->createdAt(),
            $copy->updatedAt(),
            $copy->version(),
        );
    }
}
