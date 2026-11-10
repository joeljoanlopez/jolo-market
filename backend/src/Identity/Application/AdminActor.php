<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\UserRepository;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Explicit actor check of the admin use cases: the actor must be an active admin.
 */
final readonly class AdminActor
{
    public function __construct(private UserRepository $users)
    {
    }

    public function require(string $actorId): User
    {
        try {
            $actor = $this->users->findById(UserId::fromString($actorId));
        } catch (InvalidUuid) {
            $actor = null;
        }

        if (null === $actor || !$actor->isActive() || !$actor->isAdmin()) {
            throw AdminRequired::create();
        }

        return $actor;
    }
}
