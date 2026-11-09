<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\InvalidArgument;

/**
 * A query string parameter cannot be mapped to the type the use case expects. It is a 422 like any other invalid
 * argument (PD-18).
 */
final class InvalidQueryParameter extends InvalidArgument
{
    public static function notAnInteger(string $name, string $value): self
    {
        return new self(\sprintf('The "%s" parameter must be an integer, got "%s".', $name, $value));
    }
}
