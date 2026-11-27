<?php

declare(strict_types=1);

namespace App\Tests\Support\ShoppingList;

use App\ShoppingList\Application\ManageList\ShoppingListDeleted;
use App\ShoppingList\Application\ShoppingListEvents;

final class RecordingShoppingListEvents implements ShoppingListEvents
{
    /** @var list<ShoppingListDeleted> */
    private array $published = [];

    /** @var (callable(): void)|null */
    private $onPublish;

    /**
     * @param callable(): void $check runs when an event is published, to see the state at that moment
     */
    public function onPublish(callable $check): void
    {
        $this->onPublish = $check;
    }

    public function listDeleted(ShoppingListDeleted $event): void
    {
        if (null !== $this->onPublish) {
            ($this->onPublish)();
        }
        $this->published[] = $event;
    }

    /**
     * @return list<ShoppingListDeleted>
     */
    public function published(): array
    {
        return $this->published;
    }
}
