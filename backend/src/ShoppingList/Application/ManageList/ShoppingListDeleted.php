<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\ManageList;

/**
 * Application event published after a list is removed (PD-44), so other contexts can remove what
 * belongs to it, such as its receipts (PD-63).
 */
final readonly class ShoppingListDeleted
{
    public function __construct(public string $listId)
    {
    }
}
