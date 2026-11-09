<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Id;

use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Time-ordered UUIDs (v7), so BINARY(16) primary keys are inserted in index order.
 */
final class UuidV7Generator implements IdGenerator
{
    public function generate(): Uuid
    {
        return Uuid::fromString(new UuidV7()->toRfc4122());
    }
}
