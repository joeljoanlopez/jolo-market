<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Api;

use App\ShoppingList\Application\CreateList\CreateListCommand;
use App\ShoppingList\Application\CreateList\CreateListHandler;
use App\ShoppingList\Application\GetMyLists\GetMyListsHandler;
use App\ShoppingList\Application\GetMyLists\GetMyListsQuery;
use App\ShoppingList\Application\GetMyLists\MyListSummary;
use App\ShoppingList\Application\JoinList\JoinListCommand;
use App\ShoppingList\Application\JoinList\JoinListHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * "My lists", creating a list and joining one through its link. The session user's identifier
 * is their id, passed to every use case as the actor.
 */
#[AsController]
final readonly class ListsController
{
    public function __construct(
        private GetMyListsHandler $getMyLists,
        private CreateListHandler $createList,
        private JoinListHandler $joinList,
    ) {
    }

    #[Route('/api/lists', name: 'api_lists_mine', methods: ['GET'])]
    public function mine(#[CurrentUser] UserInterface $actor): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (MyListSummary $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'role' => $list->role,
                'ownerUsername' => $list->ownerUsername,
                'itemCount' => $list->itemCount,
                'updatedAt' => $list->updatedAt->format(\DATE_ATOM),
            ],
            ($this->getMyLists)(new GetMyListsQuery($actor->getUserIdentifier())),
        ));
    }

    #[Route('/api/lists', name: 'api_lists_create', methods: ['POST'])]
    public function create(#[CurrentUser] UserInterface $actor, Request $request): JsonResponse
    {
        $id = ($this->createList)(new CreateListCommand($actor->getUserIdentifier(), ListRequestBody::of($request)->requiredString('name')));

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    /**
     * Matched before the /api/lists/{id}/... routes, so no token can be taken for a list id.
     */
    #[Route('/api/lists/join/{shareToken}', name: 'api_lists_join', methods: ['POST'], priority: 1)]
    public function join(#[CurrentUser] UserInterface $actor, string $shareToken): JsonResponse
    {
        return new JsonResponse(['id' => ($this->joinList)(new JoinListCommand($actor->getUserIdentifier(), $shareToken))]);
    }
}
