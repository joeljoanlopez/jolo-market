<?php

declare(strict_types=1);

namespace App\ShoppingList\Application;

use App\Shared\Domain\ValueObject\InvalidUuid;
use App\ShoppingList\Domain\Model\ShoppingList;
use App\ShoppingList\Domain\Model\ShoppingListNotFound;
use App\ShoppingList\Domain\Port\ShoppingListChangedConcurrently;
use App\ShoppingList\Domain\Port\ShoppingListRepository;
use App\ShoppingList\Domain\ValueObject\ShoppingListId;

/**
 * Loads a list, applies one operation and saves it (PD-43). When someone else saved the list
 * first, the whole operation runs again on a fresh copy, so edits to different items or fields
 * never undo each other and the user never sees a conflict. Operations state their intent
 * (check sets checked), so running one again is safe.
 */
final readonly class ListUpdater
{
    public const int MAX_ATTEMPTS = 5;

    public function __construct(private ShoppingListRepository $lists)
    {
    }

    /**
     * An unknown or malformed id is a ShoppingListNotFound.
     */
    public function load(string $listId): ShoppingList
    {
        try {
            $id = ShoppingListId::fromString($listId);
        } catch (InvalidUuid) {
            throw ShoppingListNotFound::withId($listId);
        }

        return $this->lists->findById($id) ?? throw ShoppingListNotFound::withId($listId);
    }

    /**
     * @template T
     *
     * @param callable(ShoppingList): T $change
     *
     * @return T
     */
    public function update(string $listId, callable $change): mixed
    {
        return $this->updateLoaded(fn (): ShoppingList => $this->load($listId), $change);
    }

    /**
     * @template T
     *
     * @param callable(): ShoppingList  $load   runs again on every attempt
     * @param callable(ShoppingList): T $change
     *
     * @return T
     */
    public function updateLoaded(callable $load, callable $change): mixed
    {
        for ($attempt = 1;; ++$attempt) {
            $list = $load();
            $result = $change($list);
            if (!$list->hasUnsavedChanges()) {
                return $result;
            }

            try {
                $this->lists->save($list);

                return $result;
            } catch (ShoppingListChangedConcurrently $changed) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $changed;
                }
            }
        }
    }
}
