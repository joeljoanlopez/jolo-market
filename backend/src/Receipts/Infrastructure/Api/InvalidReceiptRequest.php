<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Api;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidReceiptRequest extends InvalidArgument
{
    public static function wrongType(string $field, string $type): self
    {
        return new self(\sprintf('The field "%s" must be %s.', $field, $type));
    }

    public static function missing(string $field): self
    {
        return new self(\sprintf('The field "%s" is required.', $field));
    }

    public static function photoNotUploaded(): self
    {
        return new self('The "photo" file was not uploaded.');
    }
}
