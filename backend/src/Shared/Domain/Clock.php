<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Source of the current time. Inject it instead of calling `new \DateTimeImmutable()`
 * so time-dependent behavior stays testable.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
