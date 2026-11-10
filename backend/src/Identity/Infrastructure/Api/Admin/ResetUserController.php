<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Admin;

use App\Identity\Application\ManageUsers\ResetUserCommand;
use App\Identity\Application\ManageUsers\ResetUserHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class ResetUserController
{
    public function __construct(private ResetUserHandler $reset)
    {
    }

    #[Route('/api/admin/users/{id}/reset', name: 'api_admin_users_reset', methods: ['POST'])]
    public function __invoke(#[CurrentUser] SecurityUser $actor, string $id): JsonResponse
    {
        $link = ($this->reset)(new ResetUserCommand($actor->id, $id));

        return ActivationLinkJson::response($link->userId, $link->username, $link->activationToken, $link->activationExpiresAt);
    }
}
