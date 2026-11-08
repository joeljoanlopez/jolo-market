<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Base of every expected business error. Its message is safe to show to API clients,
 * so it must never carry secrets or internal details.
 */
abstract class DomainError extends \RuntimeException
{
}
