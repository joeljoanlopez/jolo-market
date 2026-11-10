<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidRequestBody extends InvalidArgument
{
    public static function notJson(): self
    {
        return new self('The request body must be a JSON object.');
    }

    public static function missingString(string $field): self
    {
        return new self(\sprintf('The field "%s" must be a string.', $field));
    }
}
