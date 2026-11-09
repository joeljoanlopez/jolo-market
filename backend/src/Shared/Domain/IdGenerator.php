<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Source of new identifiers. Inject it instead of generating ids inline,
 * so tests can predict them.
 */
interface IdGenerator
{
    public function generate(): Uuid;
}
