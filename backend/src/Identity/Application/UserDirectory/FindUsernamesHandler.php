<?php

declare(strict_types=1);

namespace App\Identity\Application\UserDirectory;

use App\Identity\Domain\Port\UserRepository;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Query API for other contexts (E3.9): they know users only by id and need their usernames.
 */
final readonly class FindUsernamesHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @return array<string, string> username by user id, in the order asked; unknown ids are left out
     */
    public function __invoke(FindUsernamesQuery $query): array
    {
        $ids = [];
        foreach ($query->userIds as $userId) {
            try {
                $ids[] = UserId::fromString($userId);
            } catch (InvalidUuid) {
                // No user can have a malformed id.
            }
        }
        if ([] === $ids) {
            return [];
        }

        $usernames = [];
        foreach ($this->users->findByIds($ids) as $user) {
            $usernames[$user->id()->value()] = $user->username()->value();
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($usernames[$id->value()])) {
                $ordered[$id->value()] = $usernames[$id->value()];
            }
        }

        return $ordered;
    }
}
