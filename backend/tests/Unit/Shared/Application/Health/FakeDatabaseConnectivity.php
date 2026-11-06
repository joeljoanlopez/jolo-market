<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Health;

use App\Shared\Application\Health\DatabaseConnectivity;

final readonly class FakeDatabaseConnectivity implements DatabaseConnectivity
{
    public function __construct(private bool $reachable)
    {
    }

    public function isReachable(): bool
    {
        return $this->reachable;
    }
}
