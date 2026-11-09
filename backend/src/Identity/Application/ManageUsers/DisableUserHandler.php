<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Identity\Domain\Port\UserRepository;

final readonly class DisableUserHandler
{
    public function __construct(
        private ManagedUsers $managedUsers,
        private UserRepository $users,
    ) {
    }

    public function __invoke(DisableUserCommand $command): void
    {
        $user = $this->managedUsers->targetOf($command->actorId, $command->userId);
        if ($user->id()->value() === $command->actorId) {
            throw CannotManageYourself::create();
        }
        $this->managedUsers->assertNotLastActiveAdmin($user);

        $user->disable();
        $this->users->save($user);
    }
}
