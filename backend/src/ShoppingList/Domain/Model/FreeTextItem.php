<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\ShoppingList\Domain\ValueObject\ItemText;

/**
 * "leche": listed but never priced.
 */
final readonly class FreeTextItem implements ItemContent
{
    public function __construct(public ItemText $text)
    {
    }
}
