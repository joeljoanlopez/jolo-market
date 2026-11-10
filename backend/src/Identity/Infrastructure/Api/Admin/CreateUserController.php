<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Admin;

use App\Identity\Application\CreateUser\CreateUserCommand;
use App\Identity\Application\CreateUser\CreateUserHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class CreateUserController
{
    public function __construct(private CreateUserHandler $createUser)
    {
    }

    #[Route('/api/admin/users', name: 'api_admin_users_create', methods: ['POST'])]
    public function __invoke(#[CurrentUser] SecurityUser $actor, Request $request): JsonResponse
    {
        $body = JsonBody::of($request);
        $created = ($this->createUser)(new CreateUserCommand($actor->id, $body->string('username'), $body->string('role')));

        return ActivationLinkJson::response($created->userId, $created->username, $created->activationToken, $created->activationExpiresAt, Response::HTTP_CREATED);
    }
}
