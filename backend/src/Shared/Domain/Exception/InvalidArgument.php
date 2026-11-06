<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * A value breaks a business rule (an invariant or a validation).
 */
abstract class InvalidArgument extends DomainError
{
}
