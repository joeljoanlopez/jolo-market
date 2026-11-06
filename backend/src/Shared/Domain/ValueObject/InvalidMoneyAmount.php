<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidMoneyAmount extends InvalidArgument
{
    public static function negative(int $cents): self
    {
        return new self(\sprintf('A money amount cannot be negative, got %d cents.', $cents));
    }

    public static function negativeMultiplier(int $multiplier): self
    {
        return new self(\sprintf('A money amount cannot be multiplied by a negative number, got %d.', $multiplier));
    }
}
