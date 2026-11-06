<?php

declare(strict_types=1);

namespace App\Shared\Application\Health;

enum HealthStatus: string
{
    case Ok = 'ok';
    case Unavailable = 'unavailable';
}
