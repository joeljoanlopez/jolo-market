<?php

declare(strict_types=1);

namespace App\ShoppingList\Application\JoinList;

use App\Shared\Domain\Exception\NotFound;

/**
 * Unknown, malformed or revoked: all the same to the client (404), and the token is never echoed.
 */
final class ShareLinkNotValid extends NotFound
{
    public static function create(): self
    {
        return new self('The share link is not valid.');
    }
}
