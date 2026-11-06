<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock;

final readonly class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        // UTC everywhere in the backend; converting to local time is a presentation concern.
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
