<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\Exception\NotFound;

/**
 * Unknown, expired and used links get the same answer (404), so a client cannot tell them apart.
 */
final class ActivationLinkNotValid extends NotFound
{
    public static function create(): self
    {
        return new self('The activation link is not valid.');
    }
}
