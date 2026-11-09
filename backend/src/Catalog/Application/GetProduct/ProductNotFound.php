<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

use App\Shared\Domain\Exception\NotFound;

final class ProductNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Product "%s" was not found.', $id));
    }
}
