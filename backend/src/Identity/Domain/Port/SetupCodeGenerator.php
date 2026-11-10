<?php

declare(strict_types=1);

namespace App\Identity\Domain\Port;

/**
 * Source of random, easy-to-type setup codes.
 */
interface SetupCodeGenerator
{
    public function generate(): string;
}
