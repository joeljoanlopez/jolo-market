<?php

declare(strict_types=1);

namespace App\Identity\Application\Login;

use App\Identity\Domain\Port\UserRepository;
use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Reloads the user of a session on every request; only an active user keeps a session.
 */
final readonly class FindSessionUserHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(FindSessionUserQuery $query): ?SessionUser
    {
        try {
            $user = $this->users->findById(UserId::fromString($query->userId));
        } catch (InvalidUuid) {
            return null;
        }

        return null !== $user && $user->isActive() ? SessionUser::of($user) : null;
    }
}
