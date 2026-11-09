<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Admin;

use App\Identity\Application\ListUsers\ListUsersHandler;
use App\Identity\Application\ListUsers\ListUsersQuery;
use App\Identity\Application\ListUsers\UserSummary;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class ListUsersController
{
    public function __construct(private ListUsersHandler $listUsers)
    {
    }

    #[Route('/api/admin/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function __invoke(#[CurrentUser] SecurityUser $actor): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (UserSummary $user): array => [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'status' => $user->status,
                'createdAt' => $user->createdAt->format(\DATE_ATOM),
                'activationExpiresAt' => $user->activationExpiresAt?->format(\DATE_ATOM),
            ],
            ($this->listUsers)(new ListUsersQuery($actor->id)),
        ));
    }
}
