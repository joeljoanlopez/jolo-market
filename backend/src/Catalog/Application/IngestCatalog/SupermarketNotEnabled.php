<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

use App\Shared\Domain\Exception\InvalidArgument;

final class SupermarketNotEnabled extends InvalidArgument
{
    public static function named(string $supermarket): self
    {
        return new self(\sprintf('Supermarket "%s" is not enabled in ENABLED_SUPERMARKETS.', $supermarket));
    }
}
