<?php

declare(strict_types=1);

namespace App\Shared\Application\Health;

final readonly class CheckHealth
{
    public function __construct(private DatabaseConnectivity $database)
    {
    }

    public function __invoke(): HealthStatus
    {
        return $this->database->isReachable() ? HealthStatus::Ok : HealthStatus::Unavailable;
    }
}
