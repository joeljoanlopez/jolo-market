<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\Login\FindSessionUserHandler;
use App\Identity\Application\Login\FindSessionUserQuery;
use App\Identity\Infrastructure\Http\SessionLogin;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class CurrentUserController
{
    public function __construct(private FindSessionUserHandler $findSessionUser)
    {
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] SecurityUser $sessionUser): JsonResponse
    {
        // Reloaded so a role changed during the session shows at once.
        $user = ($this->findSessionUser)(new FindSessionUserQuery($sessionUser->id))
            ?? throw new UnauthorizedHttpException('Session');

        return SessionLogin::currentUserResponse($user->id, $user->username, $user->role);
    }
}
