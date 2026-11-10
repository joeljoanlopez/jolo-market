<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Login\FindSessionUserHandler;
use App\Identity\Application\Login\FindSessionUserQuery;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads session users through the Identity application layer. A user who is no longer active
 * is "not found", which ends the session.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SessionUserProvider implements UserProviderInterface
{
    public function __construct(private FindSessionUserHandler $findSessionUser)
    {
    }

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        $user = ($this->findSessionUser)(new FindSessionUserQuery($identifier));
        if (null === $user) {
            $error = new UserNotFoundException();
            $error->setUserIdentifier($identifier);

            throw $error;
        }

        return SecurityUser::of($user);
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('"%s" is not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->id);
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }
}
