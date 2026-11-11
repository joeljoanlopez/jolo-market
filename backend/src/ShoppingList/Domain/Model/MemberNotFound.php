<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\Shared\Domain\Exception\NotFound;

final class MemberNotFound extends NotFound
{
    public static function withId(string $userId): self
    {
        return new self(\sprintf('User "%s" is not a member of this list.', $userId));
    }
}
