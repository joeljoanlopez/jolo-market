<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

use App\Shared\Domain\Exception\InvalidArgument;

final class TotpCodeRejected extends InvalidArgument
{
    public static function create(): self
    {
        return new self('The two-factor code is not correct.');
    }
}
