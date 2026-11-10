<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\Exception\InvalidArgument;

final class InvalidRole extends InvalidArgument
{
    public static function unknown(string $name): self
    {
        return new self(\sprintf('"%s" is not a role (ADMIN or USER).', $name));
    }
}
