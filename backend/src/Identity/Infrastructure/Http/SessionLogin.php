<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Application\Login\FindSessionUserHandler;
use App\Identity\Application\Login\FindSessionUserQuery;
use App\Identity\Application\Login\SessionUser;
use App\Identity\Infrastructure\Security\ProgrammaticLoginAuthenticator;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Opens a fully authenticated session for a user whose credentials the use cases accepted.
 */
final readonly class SessionLogin
{
    public function __construct(private Security $security)
    {
    }

    public function logIn(SessionUser $user): JsonResponse
    {
        $this->security->login(SecurityUser::of($user), ProgrammaticLoginAuthenticator::class, 'main');

        return self::currentUserResponse($user->id, $user->username, $user->role);
    }

    /**
     * Moves the current session to the user's new session version, so it survives the change
     * that ended every other session.
     */
    public function renew(FindSessionUserHandler $findSessionUser, string $userId): void
    {
        $user = $findSessionUser(new FindSessionUserQuery($userId)) ?? throw new \LogicException('The user changing credentials is active.');
        $this->logIn($user);
    }

    public static function currentUserResponse(string $id, string $username, string $role): JsonResponse
    {
        return new JsonResponse(['id' => $id, 'username' => $username, 'role' => $role]);
    }
}
