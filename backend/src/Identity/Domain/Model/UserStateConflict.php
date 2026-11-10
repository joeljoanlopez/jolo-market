<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\Exception\Conflict;

final class UserStateConflict extends Conflict
{
    public static function notPendingActivation(): self
    {
        return new self('The user is not pending activation.');
    }

    public static function notActive(): self
    {
        return new self('The user is not active.');
    }

    public static function notDisabled(): self
    {
        return new self('The user is not disabled.');
    }

    public static function disabled(): self
    {
        return new self('The user is disabled; enable them first.');
    }

    public static function noTotpReEnrollment(): self
    {
        return new self('Start the two-factor re-enrollment before confirming it.');
    }
}
