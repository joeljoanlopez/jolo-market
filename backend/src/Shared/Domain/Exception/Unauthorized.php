<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The caller could not prove who they are (wrong credentials, no session).
 */
abstract class Unauthorized extends DomainError
{
}
