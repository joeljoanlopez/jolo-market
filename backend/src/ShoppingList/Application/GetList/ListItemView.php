<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\GetList;

/**
 * $type is "text" (with $text) or "product" (with $product). $section is the section the item is
 * shown in: the one chosen by hand ($sectionOverride), else its catalog section, else "other" (PD-96).
 */
final readonly class ListItemView
{
    public const string TEXT = 'text';
    public const string PRODUCT = 'product';

    public function __construct(
        public string $id,
        public int $position,
        public int $quantity,
        public bool $checked,
        public string $type,
        public ?string $text,
        public ?ListProductView $product,
        public string $section,
        public ?string $sectionOverride,
    ) {
    }
}
