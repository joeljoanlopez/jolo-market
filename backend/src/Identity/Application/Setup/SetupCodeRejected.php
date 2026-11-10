<?php

declare(strict_types=1);

namespace App\Identity\Application\Setup;

use App\Shared\Domain\Exception\InvalidArgument;

final class SetupCodeRejected extends InvalidArgument
{
    public static function create(): self
    {
        return new self('The setup code is not correct.');
    }
}
