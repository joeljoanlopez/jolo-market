<?php

declare(strict_types=1);

namespace App\Identity\Application\Login;

use App\Shared\Domain\Exception\Unauthorized;

/**
 * One error for every failed login step (unknown user, wrong password, wrong code, inactive
 * user), so a client cannot learn which part was wrong.
 */
final class InvalidCredentials extends Unauthorized
{
    public static function create(): self
    {
        return new self('Invalid credentials.');
    }
}
