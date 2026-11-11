<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\EditItems;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * Only a product the catalog offers now can be added (PD-22): an unknown, malformed or
 * unavailable one is invalid input (422).
 */
final class ProductNotAvailable extends InvalidArgument
{
    public static function withId(?string $id): self
    {
        return new self(\sprintf('Product "%s" is not available.', $id ?? ''));
    }
}
