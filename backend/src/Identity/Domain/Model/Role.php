<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

/**
 * PD-14: an ADMIN manages users and can also use the app; a USER only uses the app.
 */
enum Role: string
{
    case ADMIN = 'ADMIN';
    case USER = 'USER';
}
