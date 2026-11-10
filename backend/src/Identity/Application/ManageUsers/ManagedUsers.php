<?php

declare(strict_types=1);

namespace App\Identity\Application\ManageUsers;

use App\Identity\Application\AdminActor;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\UserRepository;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Loads the user an admin acts on, after checking that the actor is an admin.
 */
final readonly class ManagedUsers
{
    public function __construct(
        private UserRepository $users,
        private AdminActor $adminActor,
    ) {
    }

    public function targetOf(string $actorId, string $userId): User
    {
        $this->adminActor->require($actorId);

        try {
            $id = UserId::fromString($userId);
        } catch (InvalidUuid) {
            throw UserNotFound::withId($userId);
        }

        return $this->users->findById($id) ?? throw UserNotFound::withId($userId);
    }

    /**
     * Disabling or demoting $user must leave at least one active admin (PD-36).
     */
    public function assertNotLastActiveAdmin(User $user): void
    {
        if ($user->isActive() && $user->isAdmin() && $this->users->countActiveAdmins() <= 1) {
            throw LastActiveAdmin::create();
        }
    }
}
