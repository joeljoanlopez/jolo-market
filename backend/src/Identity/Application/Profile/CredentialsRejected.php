<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

use App\Shared\Domain\Exception\InvalidArgument;

final class CredentialsRejected extends InvalidArgument
{
    public static function create(): self
    {
        return new self('The password or the two-factor code is not correct.');
    }
}
