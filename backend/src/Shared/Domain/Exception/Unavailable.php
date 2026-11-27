<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * A service the request depends on did not answer or failed, such as the search service (PD-105). It maps to 503.
 * It is not a DomainError: its message may name internal hosts, so it is only logged, never shown to API clients.
 */
abstract class Unavailable extends \RuntimeException
{
}
