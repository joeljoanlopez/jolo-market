<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidProductData extends InvalidArgument
{
    public static function blankExternalId(): self
    {
        return new self('An external product id cannot be blank.');
    }

    public static function blankName(): self
    {
        return new self('A product name cannot be blank.');
    }

    public static function emptyCategoryPath(): self
    {
        return new self('A category path needs at least one non-blank segment.');
    }

    public static function invalidUrl(string $value): self
    {
        return new self(\sprintf('"%s" is not an absolute http(s) URL.', $value));
    }

    public static function unknownUnitOfMeasure(string $unit): self
    {
        return new self(\sprintf('"%s" is not a known unit of measure.', $unit));
    }
}
