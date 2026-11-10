<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Admin;

use App\Identity\Application\ManageUsers\DisableUserCommand;
use App\Identity\Application\ManageUsers\DisableUserHandler;
use App\Identity\Application\ManageUsers\EnableUserCommand;
use App\Identity\Application\ManageUsers\EnableUserHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class UserStatusController
{
    public function __construct(
        private DisableUserHandler $disable,
        private EnableUserHandler $enable,
    ) {
    }

    #[Route('/api/admin/users/{id}/disable', name: 'api_admin_users_disable', methods: ['POST'])]
    public function disable(#[CurrentUser] SecurityUser $actor, string $id): Response
    {
        ($this->disable)(new DisableUserCommand($actor->id, $id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/admin/users/{id}/enable', name: 'api_admin_users_enable', methods: ['POST'])]
    public function enable(#[CurrentUser] SecurityUser $actor, string $id): Response
    {
        ($this->enable)(new EnableUserCommand($actor->id, $id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
