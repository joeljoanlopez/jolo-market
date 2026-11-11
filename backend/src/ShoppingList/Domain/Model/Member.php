<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\Model;

use App\Shared\Domain\ValueObject\UserId;

/**
 * A user who joined the list through its link. The owner is never a member.
 */
final readonly class Member
{
    public function __construct(
        public UserId $userId,
        public \DateTimeImmutable $joinedAt,
    ) {
    }
}
