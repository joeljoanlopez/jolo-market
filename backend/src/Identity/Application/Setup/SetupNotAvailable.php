<?php

declare(strict_types=1);

namespace App\Identity\Application\Setup;

use App\Shared\Domain\Exception\NotFound;

/**
 * Once any user exists, setup answers 404 forever (PD-14).
 */
final class SetupNotAvailable extends NotFound
{
    public static function create(): self
    {
        return new self('Not found.');
    }
}
