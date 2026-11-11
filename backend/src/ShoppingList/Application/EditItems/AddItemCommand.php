<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

/**
 * $type is "text" (with $text) or "product" (with $productId). The quantity defaults to 1.
 */
final readonly class AddItemCommand
{
    public const string TEXT = 'text';
    public const string PRODUCT = 'product';

    public function __construct(
        public string $actorId,
        public string $listId,
        public string $type,
        public ?string $text = null,
        public ?string $productId = null,
        public ?int $quantity = null,
    ) {
    }
}
