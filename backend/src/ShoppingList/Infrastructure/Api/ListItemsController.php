<?php

declare(strict_types=1);

namespace App\ShoppingList\Infrastructure\Api;

use App\ShoppingList\Application\EditItems\AddItemCommand;
use App\ShoppingList\Application\EditItems\AddItemHandler;
use App\ShoppingList\Application\EditItems\ClearCheckedItemsCommand;
use App\ShoppingList\Application\EditItems\ClearCheckedItemsHandler;
use App\ShoppingList\Application\EditItems\RemoveItemCommand;
use App\ShoppingList\Application\EditItems\RemoveItemHandler;
use App\ShoppingList\Application\EditItems\UpdateItemCommand;
use App\ShoppingList\Application\EditItems\UpdateItemHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Items of a list, edited by its owner and members.
 */
#[AsController]
final readonly class ListItemsController
{
    public function __construct(
        private AddItemHandler $addItem,
        private UpdateItemHandler $updateItem,
        private RemoveItemHandler $removeItem,
        private ClearCheckedItemsHandler $clearChecked,
    ) {
    }

    #[Route('/api/lists/{id}/items', name: 'api_lists_items_add', methods: ['POST'])]
    public function add(#[CurrentUser] UserInterface $actor, string $id, Request $request): JsonResponse
    {
        $body = ListRequestBody::of($request);
        $itemId = ($this->addItem)(new AddItemCommand(
            $actor->getUserIdentifier(),
            $id,
            $body->requiredString('type'),
            $body->string('text'),
            $body->string('productId'),
            $body->int('quantity'),
        ));

        return new JsonResponse(['id' => $itemId], Response::HTTP_CREATED);
    }

    #[Route('/api/lists/{id}/items/{itemId}', name: 'api_lists_items_update', methods: ['PATCH'])]
    public function update(#[CurrentUser] UserInterface $actor, string $id, string $itemId, Request $request): Response
    {
        $body = ListRequestBody::of($request);
        ($this->updateItem)(new UpdateItemCommand(
            $actor->getUserIdentifier(),
            $id,
            $itemId,
            $body->int('quantity'),
            $body->bool('checked'),
            $body->int('position'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/lists/{id}/items/{itemId}', name: 'api_lists_items_remove', methods: ['DELETE'])]
    public function remove(#[CurrentUser] UserInterface $actor, string $id, string $itemId): Response
    {
        ($this->removeItem)(new RemoveItemCommand($actor->getUserIdentifier(), $id, $itemId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/lists/{id}/items', name: 'api_lists_items_clear_checked', methods: ['DELETE'])]
    public function clearChecked(#[CurrentUser] UserInterface $actor, string $id, Request $request): Response
    {
        if ('true' !== $request->query->getString('checked')) {
            throw InvalidListRequest::onlyCheckedItemsCanBeCleared();
        }
        ($this->clearChecked)(new ClearCheckedItemsCommand($actor->getUserIdentifier(), $id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
