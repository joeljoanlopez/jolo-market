<?php

declare(strict_types=1);

namespace App\Identity\Application\ListUsers;

use App\Identity\Application\AdminActor;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\UserRepository;

final readonly class ListUsersHandler
{
    public function __construct(
        private UserRepository $users,
        private AdminActor $adminActor,
    ) {
    }

    /**
     * @return list<UserSummary> ordered by username
     */
    public function __invoke(ListUsersQuery $query): array
    {
        $this->adminActor->require($query->actorId);

        return array_map(
            static fn (User $user): UserSummary => new UserSummary(
                $user->id()->value(),
                $user->username()->value(),
                $user->role()->value,
                $user->status()->value,
                $user->createdAt(),
                $user->activation()?->expiresAt(),
            ),
            $this->users->findAll(),
        );
    }
}
