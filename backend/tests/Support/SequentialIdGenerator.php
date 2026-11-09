<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Deterministic ids for tests: 00000000-0000-4000-8000-000000000001, ...002, and so on.
 */
final class SequentialIdGenerator implements IdGenerator
{
    private int $next = 1;

    public function generate(): Uuid
    {
        return Uuid::fromString(\sprintf('00000000-0000-4000-8000-%012d', $this->next++));
    }
}
