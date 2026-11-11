<?php

declare(strict_types=1);

namespace App\ShoppingList\Application;

use App\Shared\Domain\ValueObject\UserId;

/**
 * Port to the Identity context (E4.9): this context knows users only by id.
 */
interface UserDirectory
{
    /**
     * @param list<UserId> $userIds
     *
     * @return array<string, string> username by user id; unknown ids are left out
     */
    public function usernamesOf(array $userIds): array;
}
