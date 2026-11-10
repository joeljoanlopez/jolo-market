<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Admin;

use App\Identity\Application\ManageUsers\ChangeUserRoleCommand;
use App\Identity\Application\ManageUsers\ChangeUserRoleHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class ChangeUserRoleController
{
    public function __construct(private ChangeUserRoleHandler $changeRole)
    {
    }

    #[Route('/api/admin/users/{id}/role', name: 'api_admin_users_role', methods: ['PUT'])]
    public function __invoke(#[CurrentUser] SecurityUser $actor, string $id, Request $request): Response
    {
        ($this->changeRole)(new ChangeUserRoleCommand($actor->id, $id, JsonBody::of($request)->string('role')));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
