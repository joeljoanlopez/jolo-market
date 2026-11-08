<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidSupermarket extends InvalidArgument
{
    public static function blankName(): self
    {
        return new self('A supermarket name cannot be blank.');
    }
}
