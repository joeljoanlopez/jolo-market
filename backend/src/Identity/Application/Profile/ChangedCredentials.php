<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

/**
 * The session that made the change moves to this version and stays logged in;
 * every other session of the user stops working.
 */
final readonly class ChangedCredentials
{
    public function __construct(public int $sessionVersion)
    {
    }
}
