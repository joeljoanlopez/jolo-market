<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The requested resource does not exist, or the actor may not know it exists.
 */
abstract class NotFound extends DomainError
{
}
