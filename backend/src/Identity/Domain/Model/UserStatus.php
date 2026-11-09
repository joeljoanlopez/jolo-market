<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

enum UserStatus: string
{
    case PENDING_ACTIVATION = 'PENDING_ACTIVATION';
    case ACTIVE = 'ACTIVE';
    case DISABLED = 'DISABLED';
}
