<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Search;

final class InvalidSynonymFile extends \RuntimeException
{
    public static function because(string $path, string $reason): self
    {
        return new self(\sprintf('The synonym file %s is invalid: %s.', $path, $reason));
    }
}
