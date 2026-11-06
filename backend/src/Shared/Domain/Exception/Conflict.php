<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The action clashes with the current state (for example, a duplicate).
 */
abstract class Conflict extends DomainError
{
}
