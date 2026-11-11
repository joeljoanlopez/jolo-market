<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Api;

use App\ShoppingList\Application\GetList\GetListHandler;
use App\ShoppingList\Application\GetList\GetListQuery;
use App\ShoppingList\Application\ManageList\DeleteListCommand;
use App\ShoppingList\Application\ManageList\DeleteListHandler;
use App\ShoppingList\Application\ManageList\LeaveListCommand;
use App\ShoppingList\Application\ManageList\LeaveListHandler;
use App\ShoppingList\Application\ManageList\RegenerateShareTokenCommand;
use App\ShoppingList\Application\ManageList\RegenerateShareTokenHandler;
use App\ShoppingList\Application\ManageList\RemoveMemberCommand;
use App\ShoppingList\Application\ManageList\RemoveMemberHandler;
use App\ShoppingList\Application\ManageList\RenameListCommand;
use App\ShoppingList\Application\ManageList\RenameListHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * One list: reading it (with ETag, E4.17) and the owner and member actions on it.
 */
#[AsController]
final readonly class ListController
{
    public function __construct(
        private GetListHandler $getList,
        private RenameListHandler $rename,
        private DeleteListHandler $delete,
        private RegenerateShareTokenHandler $regenerateShareToken,
        private RemoveMemberHandler $removeMember,
        private LeaveListHandler $leave,
    ) {
    }

    /**
     * The SPA polls this every ~5 s (PD-7); while the version stays the same it gets a bodiless 304.
     */
    #[Route('/api/lists/{id}', name: 'api_lists_get', methods: ['GET'])]
    public function get(#[CurrentUser] UserInterface $actor, string $id, Request $request): Response
    {
        $list = ($this->getList)(new GetListQuery($actor->getUserIdentifier(), $id));

        $response = new JsonResponse(ListJson::of($list));
        $response->setEtag((string) $list->version);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified($request);

        return $response;
    }

    #[Route('/api/lists/{id}', name: 'api_lists_rename', methods: ['PATCH'])]
    public function rename(#[CurrentUser] UserInterface $actor, string $id, Request $request): Response
    {
        ($this->rename)(new RenameListCommand($actor->getUserIdentifier(), $id, ListRequestBody::of($request)->requiredString('name')));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/lists/{id}', name: 'api_lists_delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] UserInterface $actor, string $id): Response
    {
        ($this->delete)(new DeleteListCommand($actor->getUserIdentifier(), $id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/lists/{id}/share-token', name: 'api_lists_share_token', methods: ['POST'])]
    public function regenerateShareToken(#[CurrentUser] UserInterface $actor, string $id): JsonResponse
    {
        $token = ($this->regenerateShareToken)(new RegenerateShareTokenCommand($actor->getUserIdentifier(), $id));

        return new JsonResponse(['shareToken' => $token]);
    }

    #[Route('/api/lists/{id}/members/{userId}', name: 'api_lists_remove_member', methods: ['DELETE'])]
    public function removeMember(#[CurrentUser] UserInterface $actor, string $id, string $userId): Response
    {
        ($this->removeMember)(new RemoveMemberCommand($actor->getUserIdentifier(), $id, $userId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/lists/{id}/leave', name: 'api_lists_leave', methods: ['POST'])]
    public function leave(#[CurrentUser] UserInterface $actor, string $id): Response
    {
        ($this->leave)(new LeaveListCommand($actor->getUserIdentifier(), $id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
