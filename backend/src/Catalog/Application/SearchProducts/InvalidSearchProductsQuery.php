<?php

declare(strict_types=1);

namespace App\Catalog\Application\SearchProducts;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidSearchProductsQuery extends InvalidArgument
{
    public static function pageBelowOne(int $page): self
    {
        return new self(\sprintf('The page must be 1 or greater, got %d.', $page));
    }

    public static function pageSizeBelowOne(int $perPage): self
    {
        return new self(\sprintf('The page size must be 1 or greater, got %d.', $perPage));
    }
}
