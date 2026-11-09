<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Port\UserRepository;

final readonly class ChangeUserRoleHandler
{
    public function __construct(
        private ManagedUsers $managedUsers,
        private UserRepository $users,
    ) {
    }

    public function __invoke(ChangeUserRoleCommand $command): void
    {
        $user = $this->managedUsers->targetOf($command->actorId, $command->userId);
        $role = Role::fromName($command->role);
        if (Role::ADMIN !== $role) {
            $this->managedUsers->assertNotLastActiveAdmin($user);
        }

        $user->changeRole($role);
        $this->users->save($user);
    }
}
