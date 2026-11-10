<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\Domain\Exception\NotFound;

/**
 * Admin areas answer 404 to anyone else, so their existence is not revealed (PD-15).
 */
final class AdminRequired extends NotFound
{
    public static function create(): self
    {
        return new self('Not found.');
    }
}
