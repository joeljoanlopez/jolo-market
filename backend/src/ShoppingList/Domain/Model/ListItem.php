<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\ShoppingList\Domain\ValueObject\ListItemId;
use App\ShoppingList\Domain\ValueObject\ProductId;
use App\ShoppingList\Domain\ValueObject\Quantity;

/**
 * An entity inside the ShoppingList aggregate; only the list changes it. Its position is its
 * place in the list. The quantity is always positive (Quantity).
 *
 * Changes state the intent (PD-43): checking sets checked, it never toggles. Each change says
 * whether it changed anything, so the list only moves its version on a real change.
 */
final class ListItem
{
    private function __construct(
        private readonly ListItemId $id,
        private readonly ItemContent $content,
        private Quantity $quantity,
        private bool $checked,
    ) {
    }

    public static function add(ListItemId $id, ItemContent $content, Quantity $quantity): self
    {
        return new self($id, $content, $quantity, false);
    }

    /**
     * Rebuilds a stored item. Only persistence adapters call it.
     */
    public static function reconstitute(ListItemId $id, ItemContent $content, Quantity $quantity, bool $checked): self
    {
        return new self($id, $content, $quantity, $checked);
    }

    public function id(): ListItemId
    {
        return $this->id;
    }

    public function content(): ItemContent
    {
        return $this->content;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }

    public function holdsProduct(ProductId $productId): bool
    {
        return $this->content instanceof ProductItem && $this->content->productId->equals($productId);
    }

    /**
     * @internal only ShoppingList calls the changing methods
     */
    public function changeQuantity(Quantity $quantity): bool
    {
        if ($this->quantity->equals($quantity)) {
            return false;
        }
        $this->quantity = $quantity;

        return true;
    }

    /**
     * @internal
     */
    public function setChecked(bool $checked): bool
    {
        if ($this->checked === $checked) {
            return false;
        }
        $this->checked = $checked;

        return true;
    }
}
