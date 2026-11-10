<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Identity\Domain\Port\UserRepository;

final readonly class EnableUserHandler
{
    public function __construct(
        private ManagedUsers $managedUsers,
        private UserRepository $users,
    ) {
    }

    public function __invoke(EnableUserCommand $command): void
    {
        $user = $this->managedUsers->targetOf($command->actorId, $command->userId);

        $user->enable();
        $this->users->save($user);
    }
}
