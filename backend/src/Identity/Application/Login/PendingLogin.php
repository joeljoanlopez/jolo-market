<?php

declare(strict_types=1);

namespace App\Identity\Application\Login;

/**
 * The password was right; the login still needs a TOTP code (PD-13).
 */
final readonly class PendingLogin
{
    public function __construct(public string $userId)
    {
    }
}
