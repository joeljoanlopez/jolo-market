<?php

declare(strict_types=1);

namespace App\ShoppingList\Application;

use App\ShoppingList\Application\ManageList\ShoppingListDeleted;

/**
 * Tells other contexts what happened to lists, without knowing who listens (E10.9).
 */
interface ShoppingListEvents
{
    public function listDeleted(ShoppingListDeleted $event): void;
}
