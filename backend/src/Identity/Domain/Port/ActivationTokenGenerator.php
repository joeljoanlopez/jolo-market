<?php

declare(strict_types=1);

namespace App\Identity\Domain\Port;

/**
 * Source of plain activation tokens: long, random and URL-safe.
 */
interface ActivationTokenGenerator
{
    public function generate(): string;
}
