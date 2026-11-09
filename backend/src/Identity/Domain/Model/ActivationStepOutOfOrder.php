<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\Exception\Conflict;

final class ActivationStepOutOfOrder extends Conflict
{
    public static function passwordMissing(): self
    {
        return new self('Set a password before enrolling two-factor authentication.');
    }

    public static function totpEnrollmentMissing(): self
    {
        return new self('Start the two-factor enrollment before confirming it.');
    }
}
