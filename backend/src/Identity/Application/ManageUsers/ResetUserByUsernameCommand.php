<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

/**
 * Sent by the server operator from the console, so there is no actor (PD-35).
 */
final readonly class ResetUserByUsernameCommand
{
    public function __construct(public string $username)
    {
    }
}
