<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Api;

use App\Receipts\Application\ConfirmReceipt\ConfirmReceiptCommand;
use App\Receipts\Application\ConfirmReceipt\ConfirmReceiptHandler;
use App\Receipts\Application\EditReceipt\AddLineCommand;
use App\Receipts\Application\EditReceipt\AddLineHandler;
use App\Receipts\Application\EditReceipt\DeleteReceiptCommand;
use App\Receipts\Application\EditReceipt\DeleteReceiptHandler;
use App\Receipts\Application\EditReceipt\LinkLineCommand;
use App\Receipts\Application\EditReceipt\LinkLineHandler;
use App\Receipts\Application\EditReceipt\RemoveLineCommand;
use App\Receipts\Application\EditReceipt\RemoveLineHandler;
use App\Receipts\Application\EditReceipt\UnlinkLineCommand;
use App\Receipts\Application\EditReceipt\UnlinkLineHandler;
use App\Receipts\Application\EditReceipt\UpdateLineCommand;
use App\Receipts\Application\EditReceipt\UpdateLineHandler;
use App\Receipts\Application\EditReceipt\UpdateReceiptCommand;
use App\Receipts\Application\EditReceipt\UpdateReceiptHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The review of a receipt by the owner and members of its list (PD-65 to PD-67, PD-129, PD-130).
 */
#[AsController]
final readonly class ReceiptEditController
{
    private const string RECEIPT = '/api/lists/{id}/receipts/{receiptId}';

    public function __construct(
        private UpdateReceiptHandler $updateReceipt,
        private AddLineHandler $addLine,
        private UpdateLineHandler $updateLine,
        private RemoveLineHandler $removeLine,
        private LinkLineHandler $linkLine,
        private UnlinkLineHandler $unlinkLine,
        private ConfirmReceiptHandler $confirmReceipt,
        private DeleteReceiptHandler $deleteReceipt,
    ) {
    }

    #[Route(self::RECEIPT, name: 'api_lists_receipts_update', methods: ['PATCH'])]
    public function update(#[CurrentUser] UserInterface $actor, string $id, string $receiptId, Request $request): Response
    {
        $body = ReceiptRequestBody::of($request);
        ($this->updateReceipt)(new UpdateReceiptCommand($actor->getUserIdentifier(), $id, $receiptId, $body->string('date'), $body->int('totalCents')));

        return self::noContent();
    }

    #[Route(self::RECEIPT.'/lines', name: 'api_lists_receipts_lines_add', methods: ['POST'])]
    public function addLine(#[CurrentUser] UserInterface $actor, string $id, string $receiptId, Request $request): JsonResponse
    {
        $body = ReceiptRequestBody::of($request);
        $lineId = ($this->addLine)(new AddLineCommand(
            $actor->getUserIdentifier(),
            $id,
            $receiptId,
            $body->requiredString('kind'),
            $body->requiredString('text'),
            $body->string('unit'),
            $body->int('quantity'),
            $body->requiredInt('amountCents'),
        ));

        return new JsonResponse(['id' => $lineId], Response::HTTP_CREATED);
    }

    #[Route(self::RECEIPT.'/lines/{lineId}', name: 'api_lists_receipts_lines_update', methods: ['PATCH'])]
    public function updateLine(#[CurrentUser] UserInterface $actor, string $id, string $receiptId, string $lineId, Request $request): Response
    {
        $body = ReceiptRequestBody::of($request);
        ($this->updateLine)(new UpdateLineCommand(
            $actor->getUserIdentifier(),
            $id,
            $receiptId,
            $lineId,
            $body->string('text'),
            $body->string('unit'),
            $body->int('quantity'),
            $body->int('amountCents'),
        ));

        return self::noContent();
    }

    #[Route(self::RECEIPT.'/lines/{lineId}', name: 'api_lists_receipts_lines_remove', methods: ['DELETE'])]
    public function removeLine(#[CurrentUser] UserInterface $actor, string $id, string $receiptId, string $lineId): Response
    {
        ($this->removeLine)(new RemoveLineCommand($actor->getUserIdentifier(), $id, $receiptId, $lineId));

        return self::noContent();
    }

    #[Route(self::RECEIPT.'/lines/{lineId}/product', name: 'api_lists_receipts_lines_link', methods: ['PUT'])]
    public function link(#[CurrentUser] UserInterface $actor, string $id, string $receiptId, string $lineId, Request $request): Response
    {
        $productId = ReceiptRequestBody::of($request)->requiredString('productId');
        ($this->linkLine)(new LinkLineCommand($actor->getUserIdentifier(), $id, $receiptId, $lineId, $productId));

        return self::noContent();
    }

    #[Route(self::RECEIPT.'/lines/{lineId}/product', name: 'api_lists_receipts_lines_unlink', methods: ['DELETE'])]
    public function unlink(#[CurrentUser] UserInterface $actor, string $id, string $receiptId, string $lineId): Response
    {
        ($this->unlinkLine)(new UnlinkLineCommand($actor->getUserIdentifier(), $id, $receiptId, $lineId));

        return self::noContent();
    }

    #[Route(self::RECEIPT.'/confirm', name: 'api_lists_receipts_confirm', methods: ['POST'])]
    public function confirm(#[CurrentUser] UserInterface $actor, string $id, string $receiptId): Response
    {
        ($this->confirmReceipt)(new ConfirmReceiptCommand($actor->getUserIdentifier(), $id, $receiptId));

        return self::noContent();
    }

    #[Route(self::RECEIPT, name: 'api_lists_receipts_delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] UserInterface $actor, string $id, string $receiptId): Response
    {
        ($this->deleteReceipt)(new DeleteReceiptCommand($actor->getUserIdentifier(), $id, $receiptId));

        return self::noContent();
    }

    private static function noContent(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
