<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Admin;

use App\Identity\Application\ManageUsers\RegenerateActivationLinkCommand;
use App\Identity\Application\ManageUsers\RegenerateActivationLinkHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class RegenerateActivationLinkController
{
    public function __construct(private RegenerateActivationLinkHandler $regenerate)
    {
    }

    #[Route('/api/admin/users/{id}/activation-link', name: 'api_admin_users_activation_link', methods: ['POST'])]
    public function __invoke(#[CurrentUser] SecurityUser $actor, string $id): JsonResponse
    {
        $link = ($this->regenerate)(new RegenerateActivationLinkCommand($actor->id, $id));

        return ActivationLinkJson::response($link->userId, $link->username, $link->activationToken, $link->activationExpiresAt);
    }
}
